<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\ListsPosts;
use App\Models\Post;
use App\Support\Locales;
use App\Support\Persian;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Search in one language's posts. Results are never indexed. */
class SearchController extends Controller
{
    use ListsPosts;

    private const MAX_WORDS = 8;

    private const MAX_RESULTS = 30;

    public function __invoke(Request $request): View
    {
        $locale = app()->getLocale();
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 200) : '';
        $words = array_slice(array_values(array_filter(explode(' ', Persian::normalize($q)))), 0, self::MAX_WORDS);

        $posts = collect();
        if ($words) {
            $query = $this->livePosts($locale)->newestFirst();
            foreach ($words as $word) {
                $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
            }
            $posts = $query->limit(self::MAX_RESULTS)->get()
                ->sortByDesc(fn (Post $p) => collect($words)->filter(fn ($w) => str_contains(Persian::normalize($p->title), $w))->count())
                ->values();
        }

        return view('site.search', [
            'q' => $q,
            'posts' => $posts,
            'searched' => $words !== [],
            'seo' => Seo::list($locale, __('site.search'), Locales::path($locale, '/search'))
                ->inEveryLanguage('/search')
                ->noindex(),
        ]);
    }
}
