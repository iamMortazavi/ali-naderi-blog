<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\EncodesUrls;
use App\Models\Page;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Locales;
use App\Support\SiteSettings;
use DateTimeInterface;
use Illuminate\Http\Response;

/** sitemap.xml (both languages, with hreflang alternates) and robots.txt. */
class SitemapController extends Controller
{
    use EncodesUrls;

    /** @var list<array{loc:string,lastmod:?DateTimeInterface,alternates:array<string,string>}> */
    private array $entries = [];

    public function sitemap(): Response
    {
        $posts = Post::query()->live()->newestFirst()
            ->get(['id', 'locale', 'slug', 'translation_key', 'published_at', 'content_updated_at']);

        // Home and the lists that exist in every language.
        foreach (['/', '/posts', '/tags', '/archives'] as $path) {
            $urls = collect(Locales::codes())->mapWithKeys(fn ($code) => [$code => Locales::url($code, $path)])->all();
            foreach (Locales::codes() as $locale) {
                $lastmod = $path === '/' || $path === '/posts'
                    ? $posts->where('locale', $locale)->map->lastModified()->filter()->max()
                    : null;
                $this->add($urls[$locale], $lastmod, $urls);
            }
        }

        // Posts, with their translations.
        foreach ($posts->groupBy('translation_key') as $versions) {
            $urls = $versions->count() > 1 ? $versions->mapWithKeys(fn (Post $p) => [$p->locale => $p->url()])->all() : [];
            foreach ($versions as $post) {
                $this->add($post->url(), $post->lastModified(), $urls);
            }
        }

        // Tags and series: one language each.
        foreach (Tag::query()->whereHas('posts', fn ($q) => $q->live())->get(['id', 'locale', 'slug']) as $tag) {
            $this->add(Locales::url($tag->locale, '/tags/'.$tag->slug));
        }
        foreach (Series::query()->whereHas('posts', fn ($q) => $q->live())->get(['id', 'locale', 'slug']) as $series) {
            $this->add(Locales::url($series->locale, '/series/'.$series->slug));
        }

        // Fixed pages, grouped by address so each lists its other-language versions.
        $pages = Page::query()->where('in_sitemap', true)->get(['id', 'locale', 'slug', 'updated_at']);
        foreach ($pages->groupBy('slug') as $versions) {
            $versions = $versions->filter(fn (Page $p) => Locales::isValid($p->locale));
            $urls = $versions->count() > 1
                ? $versions->mapWithKeys(fn (Page $p) => [$p->locale => Locales::url($p->locale, '/'.$p->slug)])->all()
                : [];
            foreach ($versions as $page) {
                $this->add(Locales::url($page->locale, '/'.$page->slug), $page->updated_at, $urls);
            }
        }

        return response()->view('site.sitemap', ['entries' => $this->entries])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (app()->environment('production')) {
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /search';
            $lines[] = 'Disallow: /en/search';
            $lines[] = '';
            $lines[] = 'Sitemap: '.SiteSettings::url().'/sitemap.xml';
        } else {
            $lines[] = '# Not the public site: keep it out of search engines.';
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * One <url>. Alternates (locale → URL) gain an x-default pointing to the Persian version;
     * every address is percent-encoded, as sitemaps require.
     *
     * @param  array<string,string>  $alternates
     */
    private function add(string $loc, ?DateTimeInterface $lastmod = null, array $alternates = []): void
    {
        if (count($alternates) > 1 && isset($alternates[Locales::DEFAULT])) {
            $alternates['x-default'] = $alternates[Locales::DEFAULT];
        }
        $this->entries[] = [
            'loc' => $this->encodeUrl($loc),
            'lastmod' => $lastmod,
            'alternates' => count($alternates) > 1 ? array_map($this->encodeUrl(...), $alternates) : [],
        ];
    }
}
