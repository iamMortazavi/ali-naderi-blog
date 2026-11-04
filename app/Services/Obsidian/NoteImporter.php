<?php

namespace App\Services\Obsidian;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Writes one checked note into the posts table, for VaultSync.
 *
 * Which post belongs to a note:
 * - a note with an `id`: the post with that (translation_key, locale); failing that, the post imported
 *   from the same file;
 * - a note without `id`: the post imported from the same file (its stored translation_key is kept, so
 *   changing the slug updates that post and the Post model adds the 301 redirect); failing that, the
 *   post with (translation_key = slug, locale), unless another note in the vault still owns it.
 */
class NoteImporter
{
    /** Bump when the conversion changes, so every post is rewritten once. */
    private const VERSION = 2;

    /** @var array<string,true> */
    private array $notePaths;

    /**
     * @param  array<string,string>  $files  every vault file → its fingerprint (from Vault::files())
     * @param  list<string>  $notePaths  every note in the posts folder in this run
     */
    public function __construct(
        private Vault $vault,
        private array $files,
        array $notePaths = [],
    ) {
        $this->notePaths = array_fill_keys($notePaths, true);
    }

    public function existing(Note $note): ?Post
    {
        $validKey = $note->key !== '' && preg_match(Note::SLUG, $note->key);
        if ($note->hasId && $validKey && ($post = $this->byKey($note->key, $note->locale))) {
            return $post;
        }
        if ($post = $this->findByPath($note->path)) {
            return $post;
        }
        if (! $note->hasId && $validKey && ($post = $this->byKey($note->key, $note->locale))) {
            $ownedByAnotherNote = $post->isFromObsidian() && $post->source_path !== $note->path
                && isset($this->notePaths[(string) $post->source_path]);

            return $ownedByAnotherNote ? null : $post;
        }

        return null;
    }

    /** The post imported from this vault file, if any. */
    public function findByPath(string $path): ?Post
    {
        return Post::withTrashed()->where('source', PostSource::Obsidian)->where('source_path', $path)->first();
    }

    /** @return 'created'|'updated'|'unchanged' */
    public function import(Note $note, LinkConverter $converter): string
    {
        $post = $this->existing($note);
        if ($post && ! $post->isFromObsidian()) {
            throw new RuntimeException("id '{$note->key}' ({$note->locale}) belongs to a post written in the panel; give the note another id");
        }
        $clash = Post::withTrashed()->where('locale', $note->locale)->where('slug', $note->slug)
            ->when($post, fn ($q) => $q->whereKeyNot($post->id))->first();
        if ($clash) {
            throw new RuntimeException("slug '{$note->slug}' is already used by the post \"{$clash->title}\"");
        }
        $key = $note->hasId ? $note->key : ($post?->translation_key ?: $this->freeKey($note->slug, $note->locale));

        $body = $converter->convert($note->body);
        $cover = $note->cover !== null ? $converter->attach($note->cover) : null;
        $audio = $note->audio !== null ? $converter->attach($note->audio) : null;
        if ($converter->errors) {
            throw new RuntimeException(implode('; ', array_unique($converter->errors)));
        }

        // The fingerprint covers what the post is made of, not the raw file: the converted body
        // changes when a linked note is published or made private, and then the post is rewritten.
        $attachments = array_map(fn (string $path) => $this->files[$path] ?? '', $converter->attachments);
        ksort($attachments);
        $hash = hash('sha256', json_encode(
            [self::VERSION, $note->path, $key, $body, $attachments, $note->normalizedProps()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
        if ($post && ! $post->trashed() && $post->source_hash === $hash && $post->status === PostStatus::Published) {
            return 'unchanged';
        }

        // Copy before saving: if a copy fails, the new fingerprint is not stored and the next run retries.
        $directory = "posts/{$note->locale}/{$key}";
        foreach ($converter->attachments as $name => $source) {
            $this->copy($source, "{$directory}/{$name}");
        }
        $media = fn (?string $ref) => $ref === null ? null : (Url::isAbsolute($ref) ? $ref : "{$directory}/{$ref}");

        DB::transaction(function () use (&$post, $note, $key, $body, $cover, $audio, $media, $hash) {
            $series = $note->series !== null ? Series::findOrCreateFor($note->locale, $note->series) : null;
            $attributes = [
                'locale' => $note->locale,
                'translation_key' => $key,
                'slug' => $note->slug,
                'title' => $note->title,
                'description' => $note->description,
                'body' => $body,
                'cover_path' => $media($cover),
                'cover_alt' => $note->coverAlt,
                'audio_url' => $media($audio),
                'video_url' => $note->video,
                'status' => PostStatus::Published,
                'published_at' => $note->date,
                'content_updated_at' => $note->updated,
                'show_toc' => $note->toc,
                'send_to_telegram' => $note->telegram,
                'series_id' => $series?->id,
                'source' => PostSource::Obsidian,
                'source_path' => $note->path,
                'source_hash' => $hash,
            ];
            if ($post) {
                if ($post->trashed()) {
                    $post->restore();
                }
                $post->fill($attributes)->save();
            } else {
                $post = Post::create($attributes);
            }
            $post->tags()->sync(collect($note->tags())->map(fn ($t) => Tag::findOrCreateFor($note->locale, $t)->id));
        });

        return $post->wasRecentlyCreated ? 'created' : 'updated';
    }

    private function byKey(string $key, string $locale): ?Post
    {
        return Post::withTrashed()->where('locale', $locale)->where('translation_key', $key)->first();
    }

    /** The slug, or slug-2, slug-3… when a post in this language already uses it as its key. */
    private function freeKey(string $slug, string $locale): string
    {
        $key = $slug;
        for ($n = 2; $this->byKey($key, $locale); $n++) {
            $key = "{$slug}-{$n}";
        }

        return $key;
    }

    /** Stream one vault file to the public disk. */
    private function copy(string $source, string $target): void
    {
        $stream = null;
        try {
            $stream = fopen($this->vault->localCopy($source), 'rb');
            $ok = $stream !== false && Storage::disk('public')->writeStream($target, $stream);
        } catch (Throwable $e) {
            throw new RuntimeException("attachment {$source} could not be copied: ".$e->getMessage());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $ok) {
            throw new RuntimeException("attachment {$source} could not be copied to the site");
        }
    }
}
