<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves public pages from a whole-page cache, so most visits need no database query.
 * Only plain GET requests are cached; the list page number (a positive whole number) is the only
 * query allowed. Only successful pages without cookies are stored. Any edit in the panel or a new
 * import clears the cache (App\Support\PageCache). Local and test copies are never cached.
 */
class CachePublicPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $page = $this->pageNumber($request);
        if ($page === false || ! $request->isMethod('GET') || app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $store = PageCache::store();
        $key = PageCache::key($request->getPathInfo(), $page);
        $hit = $store->get($key);
        if (is_array($hit) && isset($hit['body'], $hit['type'])) {
            return response($hit['body'], 200, [
                'Content-Type' => $hit['type'],
                'Cache-Control' => 'public, max-age='.PageCache::MAX_AGE,
                'X-Page-Cache' => 'hit',
            ]);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 200 && ! $response->headers->has('Set-Cookie')
            && is_string($content = $response->getContent()) && $content !== '') {
            $store->put($key, ['body' => $content, 'type' => $response->headers->get('Content-Type')], now()->addDay());
            $response->headers->set('Cache-Control', 'public, max-age='.PageCache::MAX_AGE);
            $response->headers->set('X-Page-Cache', 'miss');
        }

        return $response;
    }

    /** null: no page number; an int: a valid one; false: the request must not be cached. */
    private function pageNumber(Request $request): int|false|null
    {
        $query = $request->query();
        if (array_diff(array_keys($query), ['page']) !== []) {
            return false;
        }
        if (! array_key_exists('page', $query)) {
            return null;
        }
        $page = $query['page'];

        return is_string($page) && preg_match('/^[1-9]\d{0,5}$/', $page) ? (int) $page : false;
    }
}
