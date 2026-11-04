<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Whole-page HTML cache for visitors. Every change to posts, pages, tags or settings
 * moves to a new cache generation, so old pages are never served after an edit.
 */
final class PageCache
{
    private const VERSION_KEY = 'page-cache-version';

    public static function key(string $url): string
    {
        return 'page:'.Cache::get(self::VERSION_KEY, 1).':'.sha1($url);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }
}
