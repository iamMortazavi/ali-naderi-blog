<?php

namespace App\Support;

final class Url
{
    /** http(s) address, as opposed to a file on the public disk. */
    public static function isAbsolute(?string $value): bool
    {
        return $value !== null && (str_starts_with($value, 'https://') || str_starts_with($value, 'http://'));
    }

    /** Make a site-relative URL ("/uploads/a.png") absolute with the public site address. */
    public static function absolute(string $url): string
    {
        return self::isAbsolute($url) ? $url : SiteSettings::url().'/'.ltrim($url, '/');
    }

    /**
     * Normalised site path for comparisons and the redirects table: decoded, one leading slash,
     * no trailing slash, no host or query. "https://x.ir/tags/%D9%81/" → "/tags/ف".
     */
    public static function path(string $value): string
    {
        return '/'.trim(rawurldecode(self::split($value)[1]), '/');
    }

    /** Percent-encode each path segment once, for sitemaps, feeds and Location headers. */
    public static function encodePath(string $url): string
    {
        [$origin, $path, $rest] = self::split($url);

        return $origin.implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $path))).$rest;
    }

    /**
     * Split a URL into [scheme://host, path, ?query#fragment] with a regex: parse_url() replaces
     * some UTF-8 bytes of Persian addresses with "_" on some systems.
     *
     * @return array{0:string,1:string,2:string}
     */
    private static function split(string $url): array
    {
        preg_match('~^((?:[a-z][a-z0-9+.-]*:)?//[^/?#]*)?([^?#]*)(.*)$~is', $url, $m);

        return [$m[1] ?? '', $m[2] ?? '', $m[3] ?? ''];
    }
}
