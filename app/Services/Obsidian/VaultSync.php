<?php

namespace App\Services\Obsidian;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Series;
use App\Models\SyncRun;
use App\Models\Tag;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Imports published notes from the Obsidian vault into the posts table. One way only: Obsidian → site.
 *
 * - Only notes inside the posts folder with "publish: true" are imported; everything else stays private.
 * - A note is matched to its post by (id, lang), so renaming the file or changing the slug updates the same post.
 * - A note with errors is skipped and reported; the other notes are still imported, and the post
 *   already on the site (if any) is left as it was.
 * - A post whose note was removed or set to "publish: false" is turned back into a draft, never deleted.
 * - Posts written in the panel are never touched.
 */
class VaultSync
{
    private array $messages = [];

    public static function fromSettings(): ?Vault
    {
        $sync = SiteSettings::group('sync');

        return match ($sync['driver'] ?? 'none') {
            'local' => new LocalVault(self::localPath($sync['local_path'] ?? '')),
            'github' => new GitHubVault($sync['github_repo'], $sync['github_branch'] ?: 'main', config('services.vault.github_token')),
            default => null,
        };
    }

    private static function localPath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Set the vault folder in Settings → Obsidian sync.');
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    public function run(Vault $vault, ?string $postsFolder = null): SyncRun
    {
        $postsFolder = trim($postsFolder ?? SiteSettings::get('sync.posts_folder', 'posts'), '/');
        $run = SyncRun::create(['source' => $vault->name(), 'status' => 'running', 'started_at' => now()]);
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unpublished' => 0, 'skipped' => 0];

        try {
            $files = $vault->files();
            $run->commit = $vault->revision();

            // Index the vault the way Obsidian resolves links: by file name, anywhere.
            $allNotes = [];
            $attachmentsByName = [];
            foreach ($files as $path => $fingerprint) {
                $name = mb_strtolower(basename($path));
                if (str_ends_with($name, '.md')) {
                    $allNotes[substr($name, 0, -3)] = true;
                } else {
                    $attachmentsByName[$name][] = $path;
                }
            }

            // Read and check the notes in the posts folder.
            $notes = [];
            foreach ($files as $path => $fingerprint) {
                if (! str_starts_with($path, $postsFolder.'/') || ! str_ends_with(strtolower($path), '.md')) {
                    continue;
                }
                $note = new Note($path, $vault->read($path), $fingerprint);
                if (! $note->publish && ! $note->errors) {
                    $counts['skipped']++;

                    continue;
                }
                $notes[] = $note;
            }
            $this->checkUnique($notes);

            $valid = array_values(array_filter($notes, fn (Note $n) => ! $n->errors));
            $byStem = [];
            $byKey = [];
            foreach ($valid as $n) {
                $byStem[$n->stem()][] = $n;
                $byKey[$n->key.':'.$n->locale] = $n;
            }

            $seen = [];
            foreach ($notes as $note) {
                if ($note->errors) {
                    $this->error($note->path, $note->errors);
                    $seen[] = $this->existing($note)?->id; // keep the version already on the site

                    continue;
                }
                $converter = new LinkConverter($note, $byStem, $byKey, $allNotes, $attachmentsByName);
                try {
                    $result = $this->import($vault, $note, $converter);
                    $counts[$result]++;
                    $seen[] = $this->existing($note)?->id;
                } catch (Throwable $e) {
                    $this->error($note->path, [$e->getMessage()]);
                    $seen[] = $this->existing($note)?->id;
                }
                foreach ($converter->warnings as $warning) {
                    $this->messages[] = ['level' => 'warning', 'file' => $note->path, 'text' => $warning];
                }
            }

            // Notes that disappeared or became private: their posts go back to draft.
            $gone = Post::query()->where('source', PostSource::Obsidian)->where('status', PostStatus::Published)
                ->whereNotIn('id', array_filter($seen))->get();
            foreach ($gone as $post) {
                $post->update(['status' => PostStatus::Draft]);
                $counts['unpublished']++;
                $this->messages[] = ['level' => 'info', 'file' => $post->source_path, 'text' => 'note removed or made private; post set to draft'];
            }

            $status = collect($this->messages)->contains('level', 'error') ? 'failed' : 'success';
        } catch (Throwable $e) {
            $this->messages[] = ['level' => 'error', 'file' => null, 'text' => $e->getMessage()];
            $status = 'failed';
        }

        $run->fill([
            'status' => $status,
            'created_count' => $counts['created'],
            'updated_count' => $counts['updated'],
            'unpublished_count' => $counts['unpublished'],
            'unchanged_count' => $counts['unchanged'],
            'skipped_count' => $counts['skipped'],
            'messages' => $this->messages,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /** @param list<Note> $notes */
    private function checkUnique(array $notes): void
    {
        $slugs = [];
        $keys = [];
        foreach ($notes as $n) {
            if ($n->errors) {
                continue;
            }
            if (isset($slugs[$n->locale.':'.$n->slug])) {
                $n->errors[] = "slug '{$n->slug}' is already used by {$slugs[$n->locale.':'.$n->slug]}";
            }
            if (isset($keys[$n->locale.':'.$n->key])) {
                $n->errors[] = "id '{$n->key}' is already used by {$keys[$n->locale.':'.$n->key]}";
            }
            $slugs[$n->locale.':'.$n->slug] ??= $n->path;
            $keys[$n->locale.':'.$n->key] ??= $n->path;
        }
    }

    private function existing(Note $note): ?Post
    {
        if (! $note->key) {
            return Post::withTrashed()->where('source', PostSource::Obsidian)->where('source_path', $note->path)->first();
        }

        return Post::withTrashed()->where('locale', $note->locale)->where('translation_key', $note->key)->first();
    }

    /** @return 'created'|'updated'|'unchanged' */
    private function import(Vault $vault, Note $note, LinkConverter $converter): string
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

        $body = $converter->convert($note->body);
        $p = $note->props;
        $cover = ! empty($p['cover']) ? $converter->attach((string) $p['cover']) : null;
        $audio = ! empty($p['audio']) ? $converter->attach((string) $p['audio']) : null;
        if ($converter->errors) {
            throw new RuntimeException(implode('; ', $converter->errors));
        }

        $hash = hash('sha256', $note->fingerprint.'|'.json_encode(array_map(
            fn ($path) => $vault->files()[$path] ?? '', $converter->attachments,
        )));
        if ($post && ! $post->trashed() && $post->source_hash === $hash && $post->status === PostStatus::Published) {
            return 'unchanged';
        }

        $directory = "posts/{$note->locale}/{$note->key}";
        $disk = Storage::disk('public');
        foreach ($converter->attachments as $name => $source) {
            $disk->put("{$directory}/{$name}", file_get_contents($vault->localCopy($source)));
        }

        $media = fn (?string $ref) => $ref === null ? null : (str_starts_with($ref, 'http') ? $ref : "{$directory}/{$ref}");

        DB::transaction(function () use (&$post, $note, $body, $cover, $audio, $media, $hash, $p) {
            $series = ! empty($p['series']) ? Series::findOrCreateFor($note->locale, (string) $p['series']) : null;
            $attributes = [
                'locale' => $note->locale,
                'translation_key' => $note->key,
                'slug' => $note->slug,
                'title' => $note->title,
                'description' => isset($p['description']) ? trim((string) $p['description']) : null,
                'body' => $body,
                'cover_path' => $media($cover),
                'cover_alt' => $p['cover_alt'] ?? null,
                'audio_url' => $media($audio),
                'video_url' => $p['video'] ?? null,
                'status' => PostStatus::Published,
                'published_at' => $note->date,
                'content_updated_at' => $note->updated,
                'show_toc' => $p['toc'] ?? true,
                'send_to_telegram' => $p['telegram'] ?? true,
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

    private function error(?string $file, array $errors): void
    {
        foreach ($errors as $text) {
            $this->messages[] = ['level' => 'error', 'file' => $file, 'text' => $text];
        }
    }
}
