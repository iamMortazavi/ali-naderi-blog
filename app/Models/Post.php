<?php

namespace App\Models;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Services\Content\ImageVariants;
use App\Services\Content\MarkdownRenderer;
use App\Support\Locales;
use App\Support\PageCache;
use App\Support\Persian;
use App\Support\Url;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One post in one language. See docs/content-model.md.
 *
 * @property int $id
 * @property string $locale
 * @property string $translation_key
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $body_html
 * @property string|null $search_text
 * @property list<array{level:int,id:string,text:string}>|null $toc
 * @property int $word_count
 * @property int $reading_minutes
 * @property string|null $cover_path
 * @property string|null $cover_alt
 * @property string|null $audio_url
 * @property string|null $video_url
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $content_updated_at
 * @property bool $show_toc
 * @property bool $send_to_telegram
 * @property int|null $series_id
 * @property int|null $author_id
 * @property PostSource $source
 * @property string|null $source_path
 * @property string|null $source_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Tag> $tags
 * @property-read Series|null $series
 * @property-read User|null $author
 * @property-read TelegramMessage|null $telegramMessage
 */
#[Fillable([
    'locale', 'translation_key', 'slug', 'title', 'description', 'body', 'cover_path', 'cover_alt',
    'audio_url', 'video_url', 'status', 'published_at', 'content_updated_at', 'show_toc', 'send_to_telegram',
    'series_id', 'author_id', 'source', 'source_path', 'source_hash',
])]
class Post extends Model
{
    use SoftDeletes;

    /** Columns needed to show a post in a list (cards, archive, search, feeds of titles). */
    public const LIST_COLUMNS = [
        'id', 'locale', 'translation_key', 'slug', 'title', 'description', 'excerpt', 'published_at', 'content_updated_at',
        'word_count', 'reading_minutes', 'audio_url', 'author_id', 'source',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'source' => PostSource::class,
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'show_toc' => 'boolean',
            'send_to_telegram' => 'boolean',
            'toc' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (blank($post->translation_key)) {
                $post->translation_key = $post->freeTranslationKey();
            }
            if ($post->isDirty(['body', 'locale', 'slug', 'translation_key']) || $post->body_html === null) {
                $rendered = app(MarkdownRenderer::class)->render($post->body, $post->mediaDirectory(), $post->locale);
                $post->body_html = $rendered->html;
                $post->toc = $rendered->headings;
                $post->word_count = Persian::wordCount($rendered->plain);
                $post->reading_minutes = max(1, (int) ceil($post->word_count / 200));
                $post->search_text = Persian::normalize($post->title.' '.$post->description.' '.$rendered->plain);
                $post->excerpt = mb_strimwidth($rendered->plain, 0, 300, '…');
            } elseif ($post->isDirty(['title', 'description'])) {
                $post->search_text = Persian::normalize($post->title.' '.$post->description.' '.strip_tags((string) $post->body_html));
            }
        });

        // A published post that changes address keeps its old address working (301).
        static::updated(function (Post $post) {
            if (! $post->wasChanged('slug') || $post->getOriginal('status') !== PostStatus::Published) {
                return;
            }
            $old = Locales::path($post->locale, '/posts/'.$post->getOriginal('slug'));
            $new = $post->path();
            // The new address is a real page again: it must not redirect anywhere (avoids a→b→a loops).
            Redirect::query()->where('from_path', $new)->delete();
            Redirect::query()->updateOrCreate(['from_path' => $old], ['to_path' => $new]);
            Redirect::query()->where('to_path', $old)->update(['to_path' => $new]);
        });

        // Resized copies of a new cover are made now, not during a visitor's page view.
        static::saved(function (Post $post) {
            if ($post->wasChanged('cover_path') || $post->wasRecentlyCreated) {
                $post->prepareCover();
            }
            PageCache::flush();
        });
        static::deleted(fn () => PageCache::flush());
        static::restored(fn () => PageCache::flush());
    }

    /** @param Builder<Post> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param Builder<Post> $query */
    public function scopeLocale(Builder $query, string $locale): void
    {
        $query->where('locale', $locale);
    }

    /**
     * Newest first, with the id as tie-breaker so posts with the same date keep a stable order.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasOne<TelegramMessage, $this> */
    public function telegramMessage(): HasOne
    {
        return $this->hasOne(TelegramMessage::class);
    }

    /**
     * Other-language versions of this post that readers can see.
     *
     * @return Collection<int, static>
     */
    public function translations(): Collection
    {
        return static::query()->live()->where('translation_key', $this->translation_key)
            ->where('locale', '!=', $this->locale)->get(self::LIST_COLUMNS);
    }

    /**
     * The next newer and next older live post in the same language.
     *
     * @return array{0: Post|null, 1: Post|null}
     */
    public function neighbours(): array
    {
        $at = $this->published_at;
        $newer = static::query()->live()->locale($this->locale)
            ->where(fn ($q) => $q->where('published_at', '>', $at)->orWhere(fn ($q) => $q->where('published_at', $at)->where('id', '>', $this->id)))
            ->orderBy('published_at')->orderBy('id')->first(self::LIST_COLUMNS);
        $older = static::query()->live()->locale($this->locale)
            ->where(fn ($q) => $q->where('published_at', '<', $at)->orWhere(fn ($q) => $q->where('published_at', $at)->where('id', '<', $this->id)))
            ->newestFirst()->first(self::LIST_COLUMNS);

        return [$newer, $older];
    }

    public function isLive(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at && $this->published_at->lte(now());
    }

    public function isFromObsidian(): bool
    {
        return $this->source === PostSource::Obsidian;
    }

    public function path(): string
    {
        return Locales::path($this->locale, '/posts/'.$this->slug);
    }

    public function url(): string
    {
        return Locales::url($this->locale, '/posts/'.$this->slug);
    }

    /** Folder on the public disk for this post's images and audio. */
    public function mediaDirectory(): string
    {
        return "posts/{$this->locale}/".($this->translation_key ?: $this->slug);
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return Url::isAbsolute($this->cover_path) ? $this->cover_path : Storage::disk('public')->url($this->cover_path);
    }

    /**
     * Cover for the post page: responsive WebP copies when the cover is an uploaded file.
     *
     * @return array{src:string,srcset:?string,width:?int,height:?int,alt:string}|null
     */
    public function coverImage(): ?array
    {
        if (! $this->cover_path) {
            return null;
        }
        $variants = Url::isAbsolute($this->cover_path) ? null : app(ImageVariants::class)->for($this->cover_path);

        return [
            'src' => $variants['src'] ?? $this->coverUrl(),
            'srcset' => $variants['srcset'] ?? null,
            'width' => $variants['width'] ?? null,
            'height' => $variants['height'] ?? null,
            'alt' => (string) $this->cover_alt,
        ];
    }

    /** Make the cover's resized copies and share image ahead of time. */
    public function prepareCover(): void
    {
        if ($this->cover_path && ! Url::isAbsolute($this->cover_path)) {
            app(ImageVariants::class)->for($this->cover_path);
            app(ImageVariants::class)->shareImage($this->cover_path);
        }
    }

    public function audioSource(): ?string
    {
        if (! $this->audio_url) {
            return null;
        }

        return Url::isAbsolute($this->audio_url) ? $this->audio_url : Storage::disk('public')->url($this->audio_url);
    }

    /** Embeddable player address for YouTube and Aparat links; null for a direct video file. */
    public function videoEmbedUrl(): ?string
    {
        if (! $this->video_url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([\w-]{11})~', $this->video_url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }
        if (preg_match('~aparat\.com/v/(\w+)~', $this->video_url, $m)) {
            return 'https://www.aparat.com/video/video/embed/videohash/'.$m[1].'/vt/frame';
        }

        return null;
    }

    /** Body HTML for feed readers: every link and image absolute, no heading anchors. */
    public function feedHtml(): string
    {
        $html = preg_replace('~<a class="heading-anchor"[^>]*></a>~', '', (string) $this->body_html);
        $html = preg_replace('~\b(href|src)="#~', '$1="'.$this->url().'#', $html);
        $html = preg_replace_callback('~\b(href|src|poster)="(/[^/"][^"]*|/)"~', fn ($m) => $m[1].'="'.Url::absolute($m[2]).'"', $html);
        $html = preg_replace_callback('~\bsrcset="([^"]*)"~', function ($m) {
            $parts = array_map(function ($candidate) {
                [$url, $size] = array_pad(preg_split('/\s+/', trim($candidate), 2), 2, '');

                return trim((str_starts_with($url, '/') && ! str_starts_with($url, '//') ? Url::absolute($url) : $url).' '.$size);
            }, explode(',', $m[1]));

            return 'srcset="'.implode(', ', $parts).'"';
        }, $html);

        return str_replace(']]>', ']]]]><![CDATA[>', $html);
    }

    public function summary(int $length = 170): string
    {
        $text = $this->description ?: ($this->excerpt ?: strip_tags((string) $this->body_html));

        return trim(mb_strimwidth(preg_replace('/\s+/u', ' ', $text), 0, $length, '…'));
    }

    public function lastModified(): ?DateTimeInterface
    {
        return $this->content_updated_at && $this->published_at && $this->content_updated_at->gt($this->published_at)
            ? $this->content_updated_at
            : $this->published_at;
    }

    /**
     * The slug, or the slug with a suffix when any post (in either language) already uses that key,
     * so a post without a chosen translation is never linked to an unrelated one by accident.
     */
    private function freeTranslationKey(): string
    {
        $base = $this->slug ?: Str::lower(Str::random(8));
        $key = $base;
        for ($n = 2; static::withTrashed()->where('translation_key', $key)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }
}
