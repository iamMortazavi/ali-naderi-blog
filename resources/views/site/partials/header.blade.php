@use('App\Support\Assets')
@use('App\Support\Locales')
@use('App\Support\SiteSettings')
@php($other = Locales::other($locale))
<header class="site-header">
  <nav class="nav wrap" aria-label="{{ __('site.mainMenu') }}">
    <a class="logo" href="{{ Locales::path($locale) }}">{{ SiteSettings::title($locale) }}</a>
    <ul class="menu">
      @foreach (SiteSettings::menu($locale) as $item)
        <li><a href="{{ $item['href'] }}" @if ($item['href'] === request()->getPathInfo()) aria-current="page" @endif @if ($item['external']) rel="noopener" @endif>{{ $item['label'] }}</a></li>
      @endforeach
    </ul>
    <div class="nav-tools">
      <a class="tool lang-switch" href="{{ $seo->languageLink($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" title="{{ Locales::label($other) }}">{!! Assets::icon('globe') !!}<span>{{ Locales::label($other) }}</span></a>
      <button class="tool theme-toggle" type="button" aria-pressed="false" aria-label="{{ __('site.toggleTheme') }}" title="{{ __('site.toggleTheme') }}">
        <span class="when-light">{!! Assets::icon('moon') !!}</span><span class="when-dark">{!! Assets::icon('sun') !!}</span>
      </button>
    </div>
  </nav>
</header>
