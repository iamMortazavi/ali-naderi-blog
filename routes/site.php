<?php

use App\Http\Controllers\Site\ArchiveController;
use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NotFoundController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\TaxonomyController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
 * Public blog. Persian at the root, English under /en. Addresses are permanent once shared:
 *   /posts  /posts/{slug}  /tags  /tags/{slug}  /tags/{slug}/index.xml  /series/{slug}
 *   /archives  /search  /index.xml  /{page}
 */

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// A fixed page's slug may be anything except the site's own first segments.
$pageSlug = '^(?!(?:'.implode('|', array_map(fn ($s) => preg_quote($s, '#'), Locales::RESERVED_SEGMENTS)).')$)[a-z0-9-]+$';

// English first, so "/en…" is never taken for a Persian page.
foreach (array_reverse(Locales::ALL, true) as $locale => $config) {
    Route::prefix(ltrim($config['prefix'], '/'))
        ->name("{$locale}.")
        ->group(function () use ($locale, $pageSlug) {
            $routes = [
                Route::get('/', HomeController::class)->name('home'),
                Route::get('/posts', [PostController::class, 'index'])->name('posts'),
                Route::get('/posts/{slug}', [PostController::class, 'show'])->name('post'),
                Route::get('/tags', [TaxonomyController::class, 'tags'])->name('tags'),
                Route::get('/tags/{slug}', [TaxonomyController::class, 'tag'])->name('tag'),
                Route::get('/tags/{slug}/index.xml', [FeedController::class, 'tag'])->name('tag.feed'),
                Route::get('/series/{slug}', [TaxonomyController::class, 'series'])->name('series'),
                Route::get('/archives', ArchiveController::class)->name('archives'),
                Route::get('/search', SearchController::class)->name('search'),
                Route::get('/index.xml', [FeedController::class, 'home'])->name('feed'),
                Route::get('/{page}', PageController::class)->where('page', $pageSlug)->name('page'),
            ];
            foreach ($routes as $route) {
                $route->defaults('locale', $locale);
            }
        });
}

// Unknown address: follow a saved redirect, or show the 404 page.
Route::fallback(NotFoundController::class);
