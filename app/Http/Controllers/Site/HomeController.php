<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\ListsPosts;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The home page: the profile and the latest posts; older posts are on /posts. */
class HomeController extends Controller
{
    use ListsPosts;

    public function __invoke(Request $request): View|RedirectResponse
    {
        $locale = app()->getLocale();

        // Old paginated home addresses (/?page=2) moved to the full list.
        if ($request->query->has('page')) {
            $page = $this->requestedPage($request);
            abort_if($page === null, 404);

            return redirect(Locales::path($locale, '/posts').($page > 1 ? '?page='.$page : ''), 301);
        }

        $posts = $this->livePosts($locale)->newestFirst()->limit((int) config('blog.home_posts', 10))->get();

        return view('site.home', ['posts' => $posts, 'seo' => Seo::home($locale)]);
    }
}
