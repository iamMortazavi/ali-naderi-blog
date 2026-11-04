<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\View\View;

/** Fixed pages such as /about, per language. */
class PageController extends Controller
{
    public function __invoke(string $page): View
    {
        $locale = app()->getLocale();
        $page = Page::query()->where('locale', $locale)->where('slug', $page)->firstOrFail();
        $alternates = Page::query()->where('slug', $page->slug)->where('locale', '!=', $locale)->pluck('locale')
            ->filter(fn ($code) => Locales::isValid($code))
            ->mapWithKeys(fn ($code) => [$code => Locales::url($code, '/'.$page->slug)])
            ->all();

        return view('site.page', [
            'page' => $page,
            'seo' => Seo::page($locale, $page->title, $page->path(), $page->description, $alternates),
        ]);
    }
}
