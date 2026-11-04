<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Jalali;
use App\Support\Locales;
use App\Support\Persian;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SiteController extends Controller
{
    private const PER_PAGE = 10;

    public function home()
    {
        $locale = app()->getLocale();
        $posts = $this->live($locale)->paginate(self::PER_PAGE);

        return view('site.home', ['posts' => $posts, 'seo' => Seo::home($locale)]);
    }

    public function posts()
    {
        $locale = app()->getLocale();
        $posts = $this->live($locale)->paginate(self::PER_PAGE);

        return view('site.list', [
            'posts' => $posts,
            'heading' => __('site.posts'),
            'count' => $posts->total(),
            'seo' => Seo::list($locale, __('site.posts'), Locales::path($locale, '/posts')),
        ]);
    }

    public function post(string $slug)
    {
        $locale = app()->getLocale();
        $post = Post::query()->live()->locale($locale)->where('slug', $slug)
            ->with(['tags', 'series', 'author'])->firstOrFail();

        $newer = $this->live($locale)->where('published_at', '>', $post->published_at)->reorder('published_at')->first();
        $older = $this->live($locale)->where('published_at', '<', $post->published_at)->first();
        $related = $post->tags->isEmpty() ? collect() : $this->live($locale)->whereKeyNot($post->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $post->tags->pluck('id')))
            ->limit(3)->get();

        return view('site.post', [
            'post' => $post,
            'translations' => $post->translations(),
            'newer' => $newer,
            'older' => $older,
            'related' => $related,
            'seo' => Seo::post($post),
        ]);
    }

    public function tags()
    {
        $locale = app()->getLocale();
        $tags = Tag::query()->where('locale', $locale)
            ->whereHas('posts', fn ($q) => $q->live())
            ->withCount(['posts' => fn ($q) => $q->live()])
            ->orderByDesc('posts_count')->orderBy('name')->get();

        return view('site.tags', ['tags' => $tags, 'seo' => Seo::list($locale, __('site.tags'), Locales::path($locale, '/tags'))]);
    }

    public function tag(string $slug)
    {
        $locale = app()->getLocale();
        $tag = Tag::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $posts = $this->live($locale)->whereHas('tags', fn ($q) => $q->whereKey($tag->id))->paginate(self::PER_PAGE);
        abort_if($posts->total() === 0, 404);

        return view('site.list', [
            'posts' => $posts,
            'heading' => '#'.$tag->name,
            'parent' => [__('site.tags'), Locales::path($locale, '/tags')],
            'count' => $posts->total(),
            'feed' => route("{$locale}.tag.feed", $tag->slug),
            'seo' => Seo::list($locale, '#'.$tag->name, $tag->path()),
        ]);
    }

    public function series(string $slug)
    {
        $locale = app()->getLocale();
        $series = Series::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $posts = $this->live($locale)->where('series_id', $series->id)->reorder('published_at')->paginate(self::PER_PAGE);
        abort_if($posts->total() === 0, 404);

        return view('site.list', [
            'posts' => $posts,
            'heading' => $series->name,
            'parent' => [__('site.series'), null],
            'count' => $posts->total(),
            'seo' => Seo::list($locale, $series->name, $series->path(), $series->description),
        ]);
    }

    public function archives()
    {
        $locale = app()->getLocale();
        $groups = [];
        foreach ($this->live($locale)->get(['id', 'locale', 'slug', 'title', 'published_at']) as $post) {
            if ($locale === 'fa') {
                [$y, $m] = Jalali::fromDate($post->published_at);
                $year = Persian::digits($y);
                $month = Jalali::monthName($m);
            } else {
                $year = $post->published_at->format('Y');
                $month = $post->published_at->format('F');
            }
            $groups[$year][$month][] = $post;
        }

        return view('site.archives', [
            'groups' => $groups,
            'count' => array_sum(array_map(fn ($months) => array_sum(array_map('count', $months)), $groups)),
            'seo' => Seo::list($locale, __('site.archives'), Locales::path($locale, '/archives')),
        ]);
    }

    public function search(Request $request)
    {
        $locale = app()->getLocale();
        $q = trim((string) $request->query('q', ''));
        $words = array_slice(array_filter(explode(' ', Persian::normalize($q))), 0, 8);
        $posts = collect();
        if ($words) {
            $query = $this->live($locale);
            foreach ($words as $word) {
                $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
            }
            $posts = $query->limit(30)->get()
                ->sortByDesc(fn (Post $p) => collect($words)->filter(fn ($w) => str_contains(Persian::normalize($p->title), $w))->count())
                ->values();
        }
        $seo = Seo::list($locale, __('site.search'), Locales::path($locale, '/search'));
        $seo->noindex = true;

        return view('site.search', ['q' => $q, 'posts' => $posts, 'searched' => (bool) $words, 'seo' => $seo]);
    }

    public function page(string $page)
    {
        $locale = app()->getLocale();
        $page = Page::query()->where('locale', $locale)->where('slug', $page)->firstOrFail();

        return view('site.page', ['page' => $page, 'seo' => Seo::page($page)]);
    }

    public function notFound(Request $request)
    {
        $path = '/'.trim($request->path(), '/');
        if ($redirect = Redirect::query()->where('from_path', $path)->first()) {
            $redirect->increment('hits');

            return redirect($redirect->to_path, $redirect->status_code);
        }
        $locale = App::getLocale();
        $seo = new Seo($locale, __('site.notFound'));
        $seo->noindex = true;

        return response()->view('site.not-found', ['seo' => $seo], 404);
    }

    private function live(string $locale)
    {
        return Post::query()->live()->locale($locale)->latest('published_at');
    }
}
