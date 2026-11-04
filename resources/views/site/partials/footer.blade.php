@use('App\Support\Assets')
@use('App\Support\Dates')
@use('App\Support\Locales')
@use('App\Support\SiteSettings')
<footer class="site-footer">
  <div class="wrap footer-inner">
    <p>© {{ Dates::year($locale) }} {{ SiteSettings::authorName($locale) ?: SiteSettings::title($locale) }}</p>
    <p class="footer-links">
      <a href="{{ Locales::path($locale, '/index.xml') }}">{!! Assets::icon('rss') !!}{{ __('site.rss') }}</a>
      @if ($channel = SiteSettings::get('telegram.channel_url'))
      <a href="{{ $channel }}" rel="noopener">{!! Assets::icon('telegram') !!}{{ __('site.joinChannel') }}</a>
      @endif
    </p>
  </div>
  <a class="to-top" href="#main" aria-label="{{ __('site.backToTop') }}" title="{{ __('site.backToTop') }}">{!! Assets::icon('arrow-up') !!}</a>
</footer>
