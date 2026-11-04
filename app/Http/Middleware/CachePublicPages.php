<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves public pages from a whole-page cache, so most visits need no database query.
 * Only plain GET requests are cached (the page number is the only query allowed).
 * Any edit in the panel or a new import starts a new cache generation (App\Support\PageCache).
 */
class CachePublicPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheable = $request->isMethod('GET') && array_diff(array_keys($request->query()), ['page']) === []
            && ! app()->isLocal();
        if (! $cacheable) {
            return $next($request);
        }

        $key = PageCache::key($request->fullUrl());
        if ($hit = Cache::get($key)) {
            return response($hit['body'], 200, ['Content-Type' => $hit['type'], 'X-Page-Cache' => 'hit']);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 200 && ! $response->headers->has('Set-Cookie')) {
            Cache::put($key, ['body' => $response->getContent(), 'type' => $response->headers->get('Content-Type')], now()->addDay());
            $response->headers->set('X-Page-Cache', 'miss');
        }

        return $response;
    }
}
