<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\EncodesUrls;
use App\Models\Redirect;
use App\Support\Locales;
use App\Support\Seo;
use App\Support\SiteSettings;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Unknown addresses: follow a redirect saved in the panel (or made when a slug changed),
 * otherwise show the site's own 404 page. Also used for every 404 thrown on the public site.
 */
class NotFoundController extends Controller
{
    use EncodesUrls;

    public function __invoke(Request $request): Response|RedirectResponse
    {
        return $this->redirectFor($request) ?? $this->page($request);
    }

    public function redirectFor(Request $request): ?RedirectResponse
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }
        // Saved paths are decoded ("/tags/فلسفه"); older rows may hold the encoded form.
        $raw = '/'.trim($request->getPathInfo(), '/');
        $path = Url::path($request->getPathInfo());
        $redirect = Redirect::query()->whereIn('from_path', array_unique([$path, $raw]))->first();
        if (! $redirect || $this->pointsTo($redirect->to_path, $path)) {
            return null;
        }
        $redirect->increment('hits');

        return redirect($this->location($redirect->to_path), $redirect->status_code ?: 301);
    }

    public function page(Request $request): Response
    {
        $locale = Locales::isValid($request->route()?->defaults['locale'] ?? null)
            ? $request->route()->defaults['locale']
            : Locales::fromPath(Url::path($request->getPathInfo()));
        App::setLocale($locale);
        view()->share('locale', $locale);

        return response()->view('site.not-found', ['seo' => Seo::notFound($locale)], 404);
    }

    /** True when the target is this same address, so following it would loop. */
    private function pointsTo(string $target, string $path): bool
    {
        if (Url::isAbsolute($target)) {
            $site = parse_url(SiteSettings::url(), PHP_URL_HOST);
            if (parse_url($target, PHP_URL_HOST) !== $site) {
                return false;
            }
        }

        return $this->normalisePath($target) === $path;
    }

    /** A Location header must be ASCII: encode each segment of the target ("/tags/فلسفه"). */
    private function location(string $target): string
    {
        return $this->encodeUrl($target);
    }
}
