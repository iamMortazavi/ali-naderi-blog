<?php

namespace App\Http\Controllers\Site\Concerns;

use App\Http\Pagination\SitePaginator;
use App\Models\Post;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Shared by the controllers that show lists of posts. */
trait ListsPosts
{
    /**
     * Live posts in one language, with only the columns a list needs.
     *
     * @return Builder<Post>
     */
    protected function livePosts(string $locale): Builder
    {
        return Post::query()->live()->locale($locale)->select(Post::LIST_COLUMNS)->with('author:id,name');
    }

    /**
     * The page of a list that ?page asks for. A page number that is not a positive whole number,
     * or lies beyond the last page, is a 404, so search engines never index empty or duplicate lists.
     * Links use the site's own address ($path is the path without the language prefix).
     */
    protected function paginate(Builder $query, Request $request, string $locale, string $path): SitePaginator
    {
        $page = $this->requestedPage($request);
        abort_if($page === null, 404);

        $perPage = (int) config('blog.per_page', 10);
        $total = (clone $query)->toBase()->getCountForPagination();
        $paginator = new SitePaginator(
            $total > 0 ? $query->forPage($page, $perPage)->get() : collect(),
            $total,
            $perPage,
            $page,
            ['path' => Locales::url($locale, $path), 'pageName' => 'page'],
        );
        abort_if($page > $paginator->lastPage(), 404);

        return $paginator;
    }

    /** 1 when there is no ?page, the number when it is a positive whole number, otherwise null. */
    protected function requestedPage(Request $request): ?int
    {
        $page = $request->query('page');
        if ($page === null) {
            return 1;
        }

        return is_string($page) && preg_match('/^[1-9]\d{0,5}$/', $page) ? (int) $page : null;
    }
}
