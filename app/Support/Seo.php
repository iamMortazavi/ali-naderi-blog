<?php

namespace App\Support;

use App\Models\Post;
use App\Services\Content\ImageVariants;
use Illuminate\Support\Collection;

/**
 * Everything a page tells search engines and link previews: title, description, canonical address,
 * language alternates (hreflang), Open Graph / Twitter cards and schema.org JSON-LD.
 * It never queries the database: controllers pass in what it needs (translations, alternates).
 */
class Seo
{
    public string $title;

    public string $description = '';

    /** Absolute address of this page; null on error pages, which have none. */
    public ?string $canonical;

    /** @var array<string,string> locale → absolute URL of the same page in that language (hreflang) */
    public array $alternates = [];

    /** @var array<string,string> locale → where the language switch leads (also for pages without hreflang) */
    public array $languageLinks = [];

    /** @var list<array{url:string,title:string}> RSS feeds besides the site's own */
    public array $feeds = [];

    public string $type = 'website';

    public ?array $image = null;

    public bool $noindex = false;

    /** JSON-LD is left out on error pages. */
    public bool $structured = true;

    public ?Post $post = null;

    public array $breadcrumbs = [];

    public function __construct(public string $locale, ?string $title = null, ?string $path = '/')
    {
        $site = SiteSettings::title($locale);
        $this->title = $title ? "{$title} | {$site}" : $site;
        $this->canonical = $path === null ? null : SiteSettings::url().$path;
        $this->description = (string) SiteSettings::get("author.bio_{$locale}");
        if (! app()->environment('production')) {
            $this->noindex = true; // local and staging copies stay out of search engines
        }
    }

    public static function home(string $locale): self
    {
        return (new self($locale, null, Locales::path($locale)))->inEveryLanguage('/');
    }

    /** A list or other simple page; $path is the site path including the language prefix. */
    public static function list(string $locale, string $title, string $path, ?string $description = null): self
    {
        $seo = new self($locale, $title, $path);
        $seo->description = $description ?: $seo->description;
        $seo->breadcrumbs = [[__('site.home'), Locales::url($locale)], [$title, $seo->canonical]];

        return $seo;
    }

    /** A fixed page; $alternates holds the same page in other languages (locale → absolute URL). */
    public static function page(string $locale, string $title, string $path, ?string $description, array $alternates): self
    {
        return self::list($locale, $title, $path, $description)->withAlternates($alternates);
    }

    /** @param  Collection<int,Post>  $translations  the post's live versions in other languages */
    public static function post(Post $post, Collection $translations): self
    {
        $seo = new self($post->locale, $post->title, $post->path());
        $seo->type = 'article';
        $seo->post = $post;
        $seo->description = $post->summary();
        $seo->withAlternates($translations->mapWithKeys(fn (Post $t) => [$t->locale => $t->url()])->all());
        $seo->breadcrumbs = [
            [__('site.home'), Locales::url($post->locale)],
            [__('site.posts'), Locales::url($post->locale, '/posts')],
            [$post->title, $post->url()],
        ];
        if ($post->cover_path && ! Url::isAbsolute($post->cover_path)
            && $share = app(ImageVariants::class)->shareImage($post->cover_path)) {
            $seo->image = $share + ['alt' => $post->cover_alt ?: $post->title];
        }

        return $seo;
    }

    /** The site's 404 page: not indexed, no canonical address, no structured data. */
    public static function notFound(string $locale): self
    {
        $seo = (new self($locale, __('site.notFound'), null))->noindex();
        $seo->structured = false;

        return $seo;
    }

    /** Keep this page out of search engines. */
    public function noindex(): static
    {
        $this->noindex = true;

        return $this;
    }

    /** @param  array<string,string>  $others  locale → absolute URL of this page in other languages */
    public function withAlternates(array $others): static
    {
        if ($others === []) {
            return $this;
        }
        $this->alternates = [$this->locale => (string) $this->canonical] + $others;
        $this->languageLinks = $others + $this->languageLinks;

        return $this;
    }

    /** A page that exists at the same path in every language, such as /posts or /archives. */
    public function inEveryLanguage(string $path): static
    {
        $others = [];
        foreach (Locales::codes() as $code) {
            if ($code !== $this->locale) {
                $others[$code] = Locales::url($code, $path);
            }
        }

        return $this->withAlternates($others);
    }

    /**
     * Page $page of a list: the number goes into the title and the canonical address. Later pages
     * have no hreflang (the other language's page N is a different list), but the language switch
     * still leads to the same list.
     */
    public function forPage(int $page): static
    {
        if ($page <= 1) {
            return $this;
        }
        $label = Persian::forLocale(__('site.pageN', ['page' => $page]), $this->locale);
        $site = SiteSettings::title($this->locale);
        $this->title = str_ends_with($this->title, " | {$site}")
            ? substr($this->title, 0, -strlen(" | {$site}"))." – {$label} | {$site}"
            : "{$this->title} – {$label}";
        $this->canonical .= '?page='.$page;
        $this->alternates = [];
        if ($this->breadcrumbs !== []) {
            $this->breadcrumbs[array_key_last($this->breadcrumbs)][1] = $this->canonical;
        }

        return $this;
    }

    public function withFeed(string $url, string $title): static
    {
        $this->feeds[] = ['url' => $url, 'title' => $title];

        return $this;
    }

    /** Where the language switch leads: the same page in that language, or that language's home. */
    public function languageLink(string $locale): string
    {
        return $this->languageLinks[$locale] ?? Locales::url($locale);
    }

    /** The image for link previews: the page's own, the one chosen in Settings, or the built-in default. */
    public function shareImage(): array
    {
        if ($this->image) {
            return ['url' => Url::absolute($this->image['url'])] + $this->image;
        }
        $alt = SiteSettings::title($this->locale);
        $chosen = SiteSettings::get('seo.default_image');
        if ($chosen && $share = app(ImageVariants::class)->shareImage($chosen)) {
            return ['url' => Url::absolute($share['url']), 'width' => 1200, 'height' => 630, 'alt' => $alt];
        }

        return ['url' => SiteSettings::url().'/site/images/og-default.png', 'width' => 1200, 'height' => 630, 'alt' => $alt];
    }

    /** Open Graph locales of this page's translations. */
    public function alternateOgLocales(): array
    {
        return collect(array_keys($this->alternates))
            ->reject(fn ($code) => $code === $this->locale)
            ->map(fn ($code) => Locales::og($code))
            ->values()
            ->all();
    }

    /** schema.org graph: WebSite + Person on the home page, BlogPosting or WebPage + BreadcrumbList elsewhere. */
    public function structuredData(): array
    {
        $home = Locales::url($this->locale);
        $person = array_filter([
            '@type' => 'Person',
            '@id' => SiteSettings::url().'/#author',
            'name' => SiteSettings::authorName($this->locale) ?: SiteSettings::title($this->locale),
            'url' => $home,
            'sameAs' => collect(SiteSettings::get('social.links', []))->pluck('url')
                ->filter(fn ($u) => Url::isAbsolute((string) $u))->values()->all() ?: null,
        ]);
        $language = Locales::tag($this->locale);
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
            $audio = $post->audioSource();
            $graph[] = array_filter([
                '@type' => 'BlogPosting',
                '@id' => $post->url().'#post',
                'mainEntityOfPage' => $post->url(),
                'url' => $post->url(),
                'headline' => mb_strimwidth($post->title, 0, 110, '…'),
                'description' => $this->description,
                'image' => [$this->shareImage()['url']],
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->lastModified()?->format(DATE_ATOM),
                'inLanguage' => $language,
                'wordCount' => $post->word_count,
                'keywords' => $post->relationLoaded('tags') ? ($post->tags->pluck('name')->implode(', ') ?: null) : null,
                'isPartOf' => $post->relationLoaded('series') && $post->series
                    ? ['@type' => 'CreativeWorkSeries', 'name' => $post->series->name, 'url' => Locales::url($post->locale, '/series/'.$post->series->slug)]
                    : null,
                'audio' => $audio ? ['@type' => 'AudioObject', 'contentUrl' => Url::absolute($audio)] : null,
                'author' => $person,
                'publisher' => ['@id' => $person['@id']],
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
