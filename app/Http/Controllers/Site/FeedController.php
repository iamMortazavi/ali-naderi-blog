<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Locales;
use App\Support\SiteSettings;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    /** Full-text RSS of the latest 30 posts in one language. */
    public function home()
    {
        $locale = app()->getLocale();
        $posts = Post::query()->live()->locale($locale)->latest('published_at')->with('tags')->limit(30)->get();

        return $this->rss($locale, SiteSettings::title($locale), Locales::url($locale), route("{$locale}.feed"), $posts);
    }

    public function tag(string $slug)
    {
        $locale = app()->getLocale();
        $tag = Tag::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $posts = $tag->posts()->live()->latest('published_at')->with('tags')->limit(30)->get();

        return $this->rss($locale, '#'.$tag->name.' | '.SiteSettings::title($locale), Locales::url($locale, '/tags/'.$tag->slug),
            route("{$locale}.tag.feed", $tag->slug), $posts);
    }

    private function rss(string $locale, string $title, string $link, string $self, Collection $posts)
    {
        return response()->view('site.feed', compact('locale', 'title', 'link', 'self', 'posts'))
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    /** One sitemap for both languages, with each page's translations as hreflang alternates. */
    public function sitemap()
    {
        $entries = [];
        foreach (Locales::codes() as $locale) {
            $entries[] = ['loc' => Locales::url($locale), 'lastmod' => Post::query()->live()->locale($locale)->max('updated_at'),
                'alternates' => collect(Locales::codes())->mapWithKeys(fn ($c) => [$c => Locales::url($c)])->all()];
            foreach (['/posts', '/tags', '/archives'] as $path) {
                $entries[] = ['loc' => Locales::url($locale, $path), 'lastmod' => null, 'alternates' => []];
            }
        }

        $posts = Post::query()->live()->get(['id', 'locale', 'slug', 'translation_key', 'updated_at']);
        $byKey = $posts->groupBy('translation_key');
        foreach ($posts as $post) {
            $entries[] = [
                'loc' => $post->url(),
                'lastmod' => $post->updated_at,
                'alternates' => $byKey[$post->translation_key]->count() > 1
                    ? $byKey[$post->translation_key]->mapWithKeys(fn ($p) => [$p->locale => $p->url()])->all()
                    : [],
            ];
        }

        $tags = Tag::query()->whereHas('posts', fn ($q) => $q->live())->get();
        foreach ($tags as $tag) {
            $entries[] = ['loc' => Locales::url($tag->locale, '/tags/'.$tag->slug), 'lastmod' => null, 'alternates' => []];
        }

        foreach (Page::query()->where('in_sitemap', true)->get() as $page) {
            $entries[] = ['loc' => Locales::url($page->locale, '/'.$page->slug), 'lastmod' => $page->updated_at, 'alternates' => []];
        }

        return response()->view('site.sitemap', ['entries' => $entries])->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *'];
        if (app()->environment('production')) {
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /*/search';
            $lines[] = 'Disallow: /search';
            $lines[] = '';
            $lines[] = 'Sitemap: '.SiteSettings::url().'/sitemap.xml';
        } else {
            $lines[] = '# Not the public site: keep it out of search engines.';
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
