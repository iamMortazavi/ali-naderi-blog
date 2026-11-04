<?php

namespace App\Support;

/**
 * The two site languages. Persian is the default and lives at the root; English lives under /en.
 */
final class Locales
{
    public const DEFAULT = 'fa';

    public const ALL = [
        'fa' => ['label' => 'فارسی', 'dir' => 'rtl', 'og' => 'fa_IR', 'prefix' => ''],
        'en' => ['label' => 'English', 'dir' => 'ltr', 'og' => 'en_US', 'prefix' => '/en'],
    ];

    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function label(string $locale): string
    {
        return self::ALL[$locale]['label'];
    }

    public static function dir(string $locale): string
    {
        return self::ALL[$locale]['dir'];
    }

    /** Site path for a locale: path('en', '/posts/x') → '/en/posts/x'; path('fa', '/') → '/'. */
    public static function path(string $locale, string $path = '/'): string
    {
        $prefix = self::ALL[$locale]['prefix'];
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? ($prefix ?: '/') : $prefix.$path;
    }

    public static function url(string $locale, string $path = '/'): string
    {
        return rtrim(SiteSettings::url(), '/').self::path($locale, $path);
    }
}
