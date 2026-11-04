@use('App\Support\Assets')
@use('App\Support\Locales')
@use('App\Support\SiteSettings')
<!doctype html>
<html lang="{{ $locale }}" dir="{{ Locales::dir($locale) }}" data-look="{{ SiteSettings::look() }}" data-theme="light">
<head>
@include('site.partials.head')
</head>
<body data-temml="{{ Assets::url('vendor/temml/temml.min.js') }}" data-temml-css="{{ Assets::url('vendor/temml/Temml-Local.css') }}" data-label-copy="{{ __('site.copyCode') }}" data-label-copied="{{ __('site.copied') }}">
<a class="skip-link" href="#main">{{ __('site.skip') }}</a>
@include('site.partials.header')
<main id="main" class="main">
@yield('main')
</main>
@include('site.partials.footer')
<div class="visually-hidden" aria-live="polite" id="announcer"></div>
<script src="{{ Assets::url('js/site.js') }}" defer></script>
</body>
</html>
