<?php

use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\SiteController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
 * Public blog. Persian at the root, English under /en. Addresses are permanent once shared:
 *   /posts/{slug}  /tags  /tags/{slug}  /series/{slug}  /archives  /search  /index.xml  /{page}
 */

Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [FeedController::class, 'robots'])->name('robots');

// English first, so "/en…" is never taken for a Persian page.
foreach (array_reverse(Locales::ALL, true) as $locale => $config) {
    Route::prefix(ltrim($config['prefix'], '/'))
        ->name("{$locale}.")
        ->group(function () use ($locale) {
            $defaults = fn ($route) => $route->defaults('locale', $locale);
            $defaults(Route::get('/', [SiteController::class, 'home'])->name('home'));
            $defaults(Route::get('/posts', [SiteController::class, 'posts'])->name('posts'));
            $defaults(Route::get('/posts/{slug}', [SiteController::class, 'post'])->name('post'));
            $defaults(Route::get('/tags', [SiteController::class, 'tags'])->name('tags'));
            $defaults(Route::get('/tags/{slug}', [SiteController::class, 'tag'])->name('tag'));
            $defaults(Route::get('/tags/{slug}/index.xml', [FeedController::class, 'tag'])->name('tag.feed'));
            $defaults(Route::get('/series/{slug}', [SiteController::class, 'series'])->name('series'));
            $defaults(Route::get('/archives', [SiteController::class, 'archives'])->name('archives'));
            $defaults(Route::get('/search', [SiteController::class, 'search'])->name('search'));
            $defaults(Route::get('/index.xml', [FeedController::class, 'home'])->name('feed'));
            $defaults(Route::get('/{page}', [SiteController::class, 'page'])
                ->where('page', '(?!en$|admin$|livewire|filament|up$)[a-z0-9-]+')->name('page'));
        });
}

// Unknown address: follow a redirect saved in the panel, or show the 404 page.
Route::fallback([SiteController::class, 'notFound']);
