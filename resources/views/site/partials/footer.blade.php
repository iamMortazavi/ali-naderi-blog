@php
    use App\Support\Assets;
    use App\Support\Jalali;
    use App\Support\Locales;
    use App\Support\Persian;
    use App\Support\SiteSettings;
    $year = $locale === 'fa' ? Persian::digits(Jalali::fromDate(now())[0]) : now()->year;
@endphp
<footer class="site-footer">
  <div class="wrap footer-inner">
    <p>© {{ $year }} {{ SiteSettings::authorName($locale) ?: SiteSettings::title($locale) }}</p>
    <p class="footer-links">
      <a href="{{ Locales::path($locale, '/index.xml') }}">{!! Assets::icon('rss') !!}{{ __('site.rss') }}</a>
      @if ($channel = SiteSettings::get('telegram.channel_url'))
      <a href="{{ $channel }}" rel="noopener">{!! Assets::icon('telegram') !!}{{ __('site.joinChannel') }}</a>
      @endif
    </p>
  </div>
  <a class="to-top" href="#main" aria-label="↑">{!! Assets::icon('arrow-up') !!}</a>
</footer>
