<?php

namespace App\Models;

use App\Support\Locales;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $locale
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Post> $posts
 */
#[Fillable(['locale', 'name', 'slug', 'description'])]
class Series extends Model
{
    protected $table = 'series';

    protected static function booted(): void
    {
        static::saving(function (Series $series) {
            $series->slug = $series->slug ?: Tag::slugFor($series->name);
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public static function findOrCreateFor(string $locale, string $name): self
    {
        return static::query()->firstOrCreate(['locale' => $locale, 'slug' => Tag::slugFor($name)], ['name' => trim($name)]);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function path(): string
    {
        return Locales::path($this->locale, '/series/'.$this->slug);
    }
}
