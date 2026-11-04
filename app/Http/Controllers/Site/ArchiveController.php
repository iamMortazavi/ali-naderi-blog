<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\ListsPosts;
use App\Support\Jalali;
use App\Support\Locales;
use App\Support\Persian;
use App\Support\Seo;
use Illuminate\View\View;

/** Every post by year and month (Jalali calendar on the Persian site). */
class ArchiveController extends Controller
{
    use ListsPosts;

    public function __invoke(): View
    {
        $locale = app()->getLocale();
        $posts = $this->livePosts($locale)->without('author')->newestFirst()->get();

        $groups = [];
        foreach ($posts as $post) {
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
            'count' => $posts->count(),
            'seo' => Seo::list($locale, __('site.archives'), Locales::path($locale, '/archives'))->inEveryLanguage('/archives'),
        ]);
    }
}
