<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Whole-page HTML cache for visitors, kept in its own file store ('pages' in config/cache.php).
 * Every change to posts, pages, tags or settings clears that store, so old pages are never
 * served after an edit and old copies never pile up on disk.
 */
final class PageCache
{
    public const STORE = 'pages';

    /** How long a browser or proxy may reuse a cached page, in seconds. */
    public const MAX_AGE = 300;

    public static function store(): Repository
    {
        return Cache::store(self::STORE);
    }

    /**
     * Key for one page: the site's own address (never the Host header the visitor sent),
     * the decoded path and the list page number, if any.
     */
    public static function key(string $path, ?int $page = null): string
    {
        return 'page:'.sha1(SiteSettings::url().Url::path($path).($page !== null ? '?page='.$page : ''));
    }

    public static function flush(): void
    {
        self::store()->flush();
    }
}
