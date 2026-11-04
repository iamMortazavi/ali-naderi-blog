<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the site owner may change from the admin panel (Settings page).
 * Stored as one JSON row per group in the settings table; missing values fall back to DEFAULTS.
 * Secrets (Telegram bot token, GitHub token, database password) are never stored here: they live in .env.
 */
final class SiteSettings
{
    public const DEFAULTS = [
        'site' => [
            'url' => null, // null → APP_URL
            'title_fa' => 'وبلاگ',
            'title_en' => 'Blog',
        ],
        'look' => [
            'name' => 'paper', // paper | ink
        ],
        'author' => [
            'name_fa' => '',
            'name_en' => '',
            'bio_fa' => '',
            'bio_en' => '',
        ],
        'menu' => [
            'items' => [
                ['label_fa' => 'برچسب‌ها', 'label_en' => 'Tags', 'path' => '/tags'],
                ['label_fa' => 'آرشیو', 'label_en' => 'Archive', 'path' => '/archives'],
                ['label_fa' => 'جست‌وجو', 'label_en' => 'Search', 'path' => '/search'],
                ['label_fa' => 'درباره‌ی من', 'label_en' => 'About', 'path' => '/about'],
            ],
        ],
        'social' => [
            'links' => [],
        ],
        'telegram' => [
            'channel_url' => '',
            'languages' => ['fa'],
            'hashtags' => true,
            'footer_fa' => '',
            'footer_en' => '',
        ],
        'seo' => [
            'google_verification' => '',
            'bing_verification' => '',
            'default_image' => null, // path on the public disk
        ],
        'sync' => [
            'driver' => 'none',       // none | local | github
            'local_path' => '',       // for "local": a folder on this server
            'github_repo' => '',      // for "github": owner/name (token: VAULT_GITHUB_TOKEN in .env)
            'github_branch' => 'main',
            'posts_folder' => 'posts',
        ],
    ];

    private const CACHE_KEY = 'site-settings';

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];
            $settings = self::DEFAULTS;
            foreach ($stored as $group => $value) {
                $settings[$group] = array_replace(self::DEFAULTS[$group] ?? [], (array) $value);
            }

            return $settings;
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(self::all(), $key, $default);
    }

    public static function group(string $group): array
    {
        return self::all()[$group] ?? [];
    }

    public static function put(string $group, array $value): void
    {
        Setting::query()->updateOrCreate(['key' => $group], ['value' => $value]);
        self::forget();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        PageCache::flush();
    }

    public static function url(): string
    {
        return rtrim(self::get('site.url') ?: config('app.url'), '/');
    }

    public static function title(string $locale): string
    {
        return self::get("site.title_{$locale}") ?: config('app.name');
    }

    public static function authorName(string $locale): string
    {
        return (string) self::get("author.name_{$locale}");
    }

    /**
     * Top menu for one language: site paths become that language's address.
     *
     * @return list<array{label:string,href:string,external:bool}>
     */
    public static function menu(string $locale): array
    {
        return collect(self::get('menu.items', []))
            ->filter(fn ($item) => filled($item['path'] ?? null) && filled($item["label_{$locale}"] ?? null))
            ->map(fn ($item) => [
                'label' => $item["label_{$locale}"],
                'href' => Url::isAbsolute($item['path']) ? $item['path'] : Locales::path($locale, $item['path']),
                'external' => Url::isAbsolute($item['path']),
            ])
            ->values()
            ->all();
    }

    public static function look(): string
    {
        return in_array(self::get('look.name'), ['paper', 'ink'], true) ? self::get('look.name') : 'paper';
    }
}
