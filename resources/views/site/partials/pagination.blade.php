@use('App\Support\Persian')
@if ($paginator->hasPages())
<nav class="pagination" aria-label="{{ __('site.pagination') }}">
  @if ($paginator->previousPageUrl())<a class="page-prev" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('site.newer') }}</a>@endif
  <span class="page-number">{{ Persian::forLocale($paginator->currentPage().' / '.$paginator->lastPage(), $locale) }}</span>
  @if ($paginator->nextPageUrl())<a class="page-next" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('site.older') }}</a>@endif
</nav>
@endif
