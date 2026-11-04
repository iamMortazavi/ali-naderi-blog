<?php

namespace App\Support;

/**
 * The two site languages. Persian is the default and lives at the root; English lives under /en.
 */
final class Locales
{
    public const DEFAULT = 'fa';

    public const ALL = [
        'fa' => ['label' => 'فارسی', 'dir' => 'rtl', 'tag' => 'fa-IR', 'og' => 'fa_IR', 'prefix' => ''],
        'en' => ['label' => 'English', 'dir' => 'ltr', 'tag' => 'en-US', 'og' => 'en_US', 'prefix' => '/en'],
    ];

    /**
     * First address segments that belong to the site itself, so a page slug may not use them.
     * Folders under public/ are listed too: the web server serves them before Laravel sees the request.
     */
    public const RESERVED_SEGMENTS = [
        'en', 'posts', 'tags', 'series', 'archives', 'search', 'admin', 'livewire', 'filament', 'up',
        'index.xml', 'sitemap.xml', 'robots.txt', 'css', 'js', 'fonts', 'site', 'uploads', 'storage', 'vendor',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string,string> code → label, for select fields. */
    public static function options(): array
    {
        return array_map(fn (array $l) => $l['label'], self::ALL);
    }

    public static function isValid(?string $locale): bool
    {
        return isset(self::ALL[$locale]);
    }

    public static function label(string $locale): string
    {
        return self::ALL[$locale]['label'];
    }

    public static function dir(string $locale): string
    {
        return self::ALL[$locale]['dir'];
    }

    /** Language tag for inLanguage, <language> and similar: fa-IR, en-US. */
    public static function tag(string $locale): string
    {
        return self::ALL[$locale]['tag'];
    }

    public static function og(string $locale): string
    {
        return self::ALL[$locale]['og'];
    }

    public static function other(string $locale): string
    {
        return $locale === 'fa' ? 'en' : 'fa';
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
        return SiteSettings::url().self::path($locale, $path);
    }

    /** The locale an address belongs to: /en and /en/… are English, everything else Persian. */
    public static function fromPath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return ($path === '/en' || str_starts_with($path, '/en/')) ? 'en' : self::DEFAULT;
    }
}
