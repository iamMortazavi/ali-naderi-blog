<?php

namespace App\Models;

use App\Support\Locales;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['locale', 'name', 'slug'])]
class Tag extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            $tag->slug = $tag->slug ?: self::slugFor($tag->name);
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    /** Persian-safe slug: keeps Persian letters, joins words with dashes. */
    public static function slugFor(string $name): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($name)));

        return trim($slug, '-') ?: Str::random(6);
    }

    public static function findOrCreateFor(string $locale, string $name): self
    {
        return static::query()->firstOrCreate(
            ['locale' => $locale, 'slug' => self::slugFor($name)],
            ['name' => trim($name)],
        );
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function path(): string
    {
        return Locales::path($this->locale, '/tags/'.$this->slug);
    }
}
