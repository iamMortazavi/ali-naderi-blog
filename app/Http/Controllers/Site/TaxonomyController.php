<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\ListsPosts;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Locales;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tags (the list and one tag's posts) and series. */
class TaxonomyController extends Controller
{
    use ListsPosts;

    public function tags(): View
    {
        $locale = app()->getLocale();
        $tags = Tag::query()->where('locale', $locale)
            ->whereHas('posts', fn ($q) => $q->live())
            ->withCount(['posts' => fn ($q) => $q->live()])
            ->orderByDesc('posts_count')->orderBy('name')->get();

        return view('site.tags', [
            'tags' => $tags,
            'seo' => Seo::list($locale, __('site.tags'), Locales::path($locale, '/tags'))->inEveryLanguage('/tags'),
        ]);
    }

    public function tag(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $tag = Tag::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $query = $this->livePosts($locale)->whereHas('tags', fn ($q) => $q->whereKey($tag->id))->newestFirst();
        $posts = $this->paginate($query, $request, $locale, '/tags/'.$tag->slug);
        abort_if($posts->total() === 0, 404);

        $feed = Locales::path($locale, '/tags/'.$tag->slug.'/index.xml');
        $feedTitle = '#'.$tag->name.' | '.SiteSettings::title($locale);

        return view('site.list', [
            'posts' => $posts,
            'heading' => '#'.$tag->name,
            'parent' => [__('site.tags'), Locales::path($locale, '/tags')],
            'count' => $posts->total(),
            'feed' => $feed,
            'seo' => Seo::list($locale, '#'.$tag->name, $tag->path())
                ->withFeed(Locales::url($locale, '/tags/'.$tag->slug.'/index.xml'), $feedTitle)
                ->forPage($posts->currentPage()),
        ]);
    }

    /** A series reads in order: oldest part first. */
    public function series(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $series = Series::query()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $query = $this->livePosts($locale)->where('series_id', $series->id)->orderBy('published_at')->orderBy('id');
        $posts = $this->paginate($query, $request, $locale, '/series/'.$series->slug);
        abort_if($posts->total() === 0, 404);

        return view('site.list', [
            'posts' => $posts,
            'heading' => $series->name,
            'parent' => [__('site.series'), null],
            'count' => $posts->total(),
            'seo' => Seo::list($locale, $series->name, $series->path(), $series->description)
                ->forPage($posts->currentPage()),
        ]);
    }
}
