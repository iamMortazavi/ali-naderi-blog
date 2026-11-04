<?php

namespace App\Models;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Services\Content\MarkdownRenderer;
use App\Support\Locales;
use App\Support\PageCache;
use App\Support\Persian;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'locale', 'translation_key', 'slug', 'title', 'description', 'body', 'cover_path', 'cover_alt',
    'audio_url', 'video_url', 'status', 'published_at', 'content_updated_at', 'show_toc', 'send_to_telegram',
    'series_id', 'author_id', 'source', 'source_path', 'source_hash',
])]
class Post extends Model
{
    use SoftDeletes;

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
            $post->translation_key = $post->translation_key ?: $post->slug;
            if ($post->isDirty(['body', 'locale', 'slug', 'translation_key']) || $post->body_html === null) {
                $rendered = app(MarkdownRenderer::class)->render($post->body, $post->mediaDirectory(), $post->locale);
                $post->body_html = $rendered->html;
                $post->toc = $rendered->headings;
                $post->word_count = Persian::wordCount($rendered->plain);
                $post->reading_minutes = max(1, (int) ceil($post->word_count / 200));
                $post->search_text = Persian::normalize($post->title.' '.$post->description.' '.$rendered->plain);
            } elseif ($post->isDirty(['title', 'description'])) {
                $post->search_text = Persian::normalize($post->title.' '.$post->description.' '.strip_tags($post->body_html));
            }
        });

        // A published post that changes address keeps its old address working (301).
        static::updated(function (Post $post) {
            if ($post->wasChanged('slug') && $post->getOriginal('status') === PostStatus::Published) {
                $old = Locales::path($post->locale, '/posts/'.$post->getOriginal('slug'));
                Redirect::query()->updateOrCreate(['from_path' => $old], ['to_path' => $post->path()]);
                Redirect::query()->where('to_path', $old)->update(['to_path' => $post->path()]);
            }
        });

        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function scopeLive(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeLocale(Builder $query, string $locale): void
    {
        $query->where('locale', $locale);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function telegramMessage(): HasOne
    {
        return $this->hasOne(TelegramMessage::class);
    }

    /** Other-language versions of this post that readers can see. */
    public function translations(): Collection
    {
        return static::query()->live()->where('translation_key', $this->translation_key)
            ->where('locale', '!=', $this->locale)->get();
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

        return str_starts_with($this->cover_path, 'http') ? $this->cover_path : Storage::disk('public')->url($this->cover_path);
    }

    public function audioSource(): ?string
    {
        if (! $this->audio_url) {
            return null;
        }

        return str_starts_with($this->audio_url, 'http') ? $this->audio_url : Storage::disk('public')->url($this->audio_url);
    }

    public function summary(int $length = 170): string
    {
        $text = $this->description ?: strip_tags((string) $this->body_html);

        return trim(mb_strimwidth(preg_replace('/\s+/u', ' ', $text), 0, $length, '…'));
    }

    public function lastModified(): ?\DateTimeInterface
    {
        return $this->content_updated_at && $this->content_updated_at->gt($this->published_at)
            ? $this->content_updated_at
            : $this->published_at;
    }
}
