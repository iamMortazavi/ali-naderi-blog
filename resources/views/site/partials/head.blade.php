@php
    use App\Support\Assets;
    use App\Support\Locales;
    use App\Support\SiteSettings;
    $image = $seo->shareImage();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonical }}">
@if ($seo->noindex)
<meta name="robots" content="noindex, follow">
@endif
@foreach ($seo->alternates as $code => $url)
<link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
@if ($code === Locales::DEFAULT)
<link rel="alternate" hreflang="x-default" href="{{ $url }}">
@endif
@endforeach
<link rel="alternate" type="application/rss+xml" href="{{ Locales::url($locale, '/index.xml') }}" title="{{ SiteSettings::title($locale) }}">
<meta property="og:site_name" content="{{ SiteSettings::title($locale) }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->post?->title ?? $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:locale" content="{{ Locales::ALL[$locale]['og'] }}">
<meta property="og:image" content="{{ $image['url'] }}">
<meta property="og:image:width" content="{{ $image['width'] }}">
<meta property="og:image:height" content="{{ $image['height'] }}">
<meta property="og:image:alt" content="{{ $image['alt'] }}">
@if ($seo->post)
<meta property="article:published_time" content="{{ $seo->post->published_at->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $seo->post->lastModified()->toIso8601String() }}">
@foreach ($seo->post->tags as $tag)
<meta property="article:tag" content="{{ $tag->name }}">
@endforeach
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->post?->title ?? $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">
<meta name="twitter:image" content="{{ $image['url'] }}">
<script type="application/ld+json">{!! $seo->jsonLd() !!}</script>
@if ($code = SiteSettings::get('seo.google_verification'))
<meta name="google-site-verification" content="{{ $code }}">
@endif
@if ($code = SiteSettings::get('seo.bing_verification'))
<meta name="msvalidate.01" content="{{ $code }}">
@endif
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#16181d" media="(prefers-color-scheme: dark)">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/site/fonts/Vazirmatn-wght.woff2" as="font" type="font/woff2" crossorigin>
{{-- Runs before the page paints: picks light/dark, and ?look=ink previews another look. --}}
<script>
(function(){var d=document.documentElement,t;try{t=localStorage.getItem("theme")}catch(e){}
if(t!=="dark"&&t!=="light"){t=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"}
d.dataset.theme=t;var l=new URLSearchParams(location.search).get("look");if(l&&/^[a-z]+$/.test(l))d.dataset.look=l})();
</script>
<style>@font-face{font-family:"Vazirmatn";src:url("/site/fonts/Vazirmatn-wght.woff2") format("woff2");font-weight:100 900;font-display:swap}{!! Assets::inlineCss() !!}</style>
