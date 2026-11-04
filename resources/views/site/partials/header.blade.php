@php
    use App\Support\Assets;
    use App\Support\Locales;
    use App\Support\SiteSettings;
    $other = $locale === 'fa' ? 'en' : 'fa';
    $otherUrl = $seo->alternates[$other] ?? Locales::url($other);
    $current = request()->getPathInfo();
@endphp
<header class="site-header">
  <nav class="nav wrap" aria-label="{{ __('site.home') }}">
    <a class="logo" href="{{ Locales::path($locale) }}">{{ SiteSettings::title($locale) }}</a>
    <ul class="menu">
      @foreach (SiteSettings::get('menu.items', []) as $item)
        @php
            $external = str_starts_with($item['path'] ?? '', 'http');
            $href = $external ? $item['path'] : Locales::path($locale, $item['path'] ?? '/');
        @endphp
        <li><a href="{{ $href }}" @if ($href === $current) aria-current="page" @endif @if ($external) rel="noopener" @endif>{{ $item['label_'.$locale] ?? '' }}</a></li>
      @endforeach
    </ul>
    <div class="nav-tools">
      <a class="tool lang-switch" href="{{ $otherUrl }}" hreflang="{{ $other }}" lang="{{ $other }}" title="{{ Locales::label($other) }}">{!! Assets::icon('globe') !!}<span>{{ Locales::label($other) }}</span></a>
      <button class="tool theme-toggle" type="button" aria-label="{{ __('site.toggleTheme') }}" title="{{ __('site.toggleTheme') }}">
        <span class="when-light">{!! Assets::icon('moon') !!}</span><span class="when-dark">{!! Assets::icon('sun') !!}</span>
      </button>
    </div>
  </nav>
</header>
