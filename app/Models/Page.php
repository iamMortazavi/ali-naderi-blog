<?php

namespace App\Models;

use App\Services\Content\MarkdownRenderer;
use App\Support\Locales;
use App\Support\PageCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Fixed pages such as "about", one row per language.
 *
 * @property int $id
 * @property string $locale
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string $body
 * @property string|null $body_html
 * @property bool $in_sitemap
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'slug', 'title', 'description', 'body', 'in_sitemap'])]
class Page extends Model
{
    protected function casts(): array
    {
        return ['in_sitemap' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if ($page->isDirty('body') || $page->body_html === null) {
                $page->body_html = app(MarkdownRenderer::class)->render($page->body, "pages/{$page->locale}", $page->locale)->html;
            }
        });
        static::saved(fn () => PageCache::flush());
        static::deleted(fn () => PageCache::flush());
    }

    public function path(): string
    {
        return Locales::path($this->locale, '/'.$this->slug);
    }
}
