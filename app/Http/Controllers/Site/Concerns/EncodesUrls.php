<?php

namespace App\Http\Controllers\Site\Concerns;

use App\Support\Url;

/** URL helpers for Persian addresses used by the sitemap, feeds and redirects (see App\\Support\\Url). */
trait EncodesUrls
{
    /** Percent-encode each path segment ("https://x.ir/tags/فلسفه" → ".../tags/%D9%81..."), once. */
    protected function encodeUrl(string $url): string
    {
        return Url::encodePath($url);
    }

    /** Decoded site path without host, query or trailing slash: "/tags/%D9%81/" → "/tags/ف". */
    protected function normalisePath(string $url): string
    {
        return Url::path($url);
    }
}
