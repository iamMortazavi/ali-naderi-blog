@php
    use App\Support\Assets;
    use App\Support\Locales;
    use App\Support\SiteSettings;
    $dir = Locales::dir($locale);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}" data-look="{{ SiteSettings::look() }}" data-theme="light">
<head>
@include('site.partials.head')
</head>
<body data-temml="{{ Assets::url('vendor/temml/temml.min.js') }}">
<a class="skip-link" href="#main">{{ __('site.skip') }}</a>
@include('site.partials.header')
<main id="main" class="main">
@yield('main')
</main>
@include('site.partials.footer')
<script src="{{ Assets::url('js/site.js') }}" defer></script>
</body>
</html>
