<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Post;
use App\Services\Content\ImageVariants;

/**
 * Everything a page tells search engines and link previews: title, description, canonical address,
 * language alternates (hreflang), Open Graph / Twitter cards and schema.org JSON-LD.
 */
class Seo
{
    public string $title;

    public string $description = '';

    public string $canonical;

    /** @var array<string,string> locale → absolute URL of the same page in that language */
    public array $alternates = [];

    public string $type = 'website';

    public ?array $image = null;

    public bool $noindex = false;

    public ?Post $post = null;

    public array $breadcrumbs = [];

    public function __construct(public string $locale, ?string $title = null, string $path = '/')
    {
        $site = SiteSettings::title($locale);
        $this->title = $title ? "{$title} | {$site}" : $site;
        $this->canonical = SiteSettings::url().$path;
        $this->description = (string) SiteSettings::get("author.bio_{$locale}");
        if (! app()->environment('production')) {
            $this->noindex = true; // local and staging copies stay out of search engines
        }
    }

    public static function home(string $locale): self
    {
        $seo = new self($locale, null, Locales::path($locale));
        foreach (Locales::codes() as $code) {
            $seo->alternates[$code] = Locales::url($code);
        }

        return $seo;
    }

    public static function list(string $locale, string $title, string $path, ?string $description = null): self
    {
        $seo = new self($locale, $title, $path);
        $seo->description = $description ?: $seo->description;
        $seo->breadcrumbs = [[__('site.home'), Locales::url($locale)], [$title, $seo->canonical]];

        return $seo;
    }

    public static function page(Page $page): self
    {
        $seo = self::list($page->locale, $page->title, $page->path(), $page->description);
        foreach (Page::query()->where('slug', $page->slug)->pluck('locale') as $code) {
            $seo->alternates[$code] = Locales::url($code, '/'.$page->slug);
        }

        return $seo;
    }

    public static function post(Post $post): self
    {
        $seo = new self($post->locale, $post->title, $post->path());
        $seo->type = 'article';
        $seo->post = $post;
        $seo->description = $post->summary();
        $seo->alternates[$post->locale] = $post->url();
        foreach ($post->translations() as $translation) {
            $seo->alternates[$translation->locale] = $translation->url();
        }
        $seo->breadcrumbs = [
            [__('site.home'), Locales::url($post->locale)],
            [__('site.posts'), Locales::url($post->locale, '/posts')],
            [$post->title, $post->url()],
        ];
        if ($post->cover_path && ! str_starts_with($post->cover_path, 'http')
            && $share = app(ImageVariants::class)->shareImage($post->cover_path)) {
            $seo->image = $share + ['alt' => $post->cover_alt ?: $post->title];
        }

        return $seo;
    }

    /** The image for link previews: the page's own, the one chosen in Settings, or the built-in default. */
    public function shareImage(): array
    {
        if ($this->image) {
            return ['url' => $this->absolute($this->image['url'])] + $this->image;
        }
        $chosen = SiteSettings::get('seo.default_image');
        if ($chosen && $share = app(ImageVariants::class)->shareImage($chosen)) {
            return ['url' => $this->absolute($share['url']), 'width' => 1200, 'height' => 630, 'alt' => SiteSettings::title($this->locale)];
        }

        return ['url' => SiteSettings::url().'/site/images/og-default.png', 'width' => 1200, 'height' => 630, 'alt' => SiteSettings::title($this->locale)];
    }

    public function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : SiteSettings::url().$url;
    }

    /** schema.org graph: WebSite + Person on the home page, BlogPosting + BreadcrumbList on posts. */
    public function structuredData(): array
    {
        $home = Locales::url($this->locale);
        $person = array_filter([
            '@type' => 'Person',
            '@id' => $home.'#author',
            'name' => SiteSettings::authorName($this->locale) ?: SiteSettings::title($this->locale),
            'url' => $home,
            'sameAs' => collect(SiteSettings::get('social.links', []))->pluck('url')
                ->filter(fn ($u) => str_starts_with((string) $u, 'http'))->values()->all() ?: null,
        ]);
        $language = $this->locale === 'fa' ? 'fa-IR' : 'en-US';
        $graph = [];

        if ($this->breadcrumbs === []) {
            $graph[] = [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'url' => $home,
                'name' => SiteSettings::title($this->locale),
                'description' => $this->description,
                'inLanguage' => $language,
                'publisher' => ['@id' => $person['@id']],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => Locales::url($this->locale, '/search').'?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ];
            $graph[] = $person;

            return ['@context' => 'https://schema.org', '@graph' => $graph];
        }

        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($this->breadcrumbs)->values()->map(fn ($crumb, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1],
            ])->all(),
        ];

        if ($post = $this->post) {
            $graph[] = array_filter([
                '@type' => 'BlogPosting',
                '@id' => $post->url().'#post',
                'mainEntityOfPage' => $post->url(),
                'url' => $post->url(),
                'headline' => mb_strimwidth($post->title, 0, 110, '…'),
                'description' => $this->description,
                'image' => [$this->shareImage()['url']],
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->lastModified()?->toIso8601String(),
                'inLanguage' => $language,
                'wordCount' => $post->word_count,
                'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
                'isPartOf' => $post->series ? ['@type' => 'CreativeWorkSeries', 'name' => $post->series->name] : null,
                'audio' => $post->audioSource() ? ['@type' => 'AudioObject', 'contentUrl' => $this->absolute($post->audioSource())] : null,
                'author' => $person,
                'publisher' => $person,
            ]);
        } else {
            $graph[] = ['@type' => 'WebPage', 'url' => $this->canonical, 'name' => $this->title,
                'description' => $this->description, 'inLanguage' => $language];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    public function jsonLd(): string
    {
        return json_encode($this->structuredData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
}
