<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Sets the page language from the route (Persian at /, English under /en). */
class SetSiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route()?->defaults['locale']
            ?? ($request->is('en') || $request->is('en/*') ? 'en' : Locales::DEFAULT);
        App::setLocale($locale);
        view()->share('locale', $locale);

        return $next($request);
    }
}
