@php use App\Support\Persian; @endphp
@if ($paginator->hasPages())
<nav class="pagination" aria-label="{{ __('site.page') }}">
  @if ($paginator->previousPageUrl())<a class="page-prev" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('site.newer') }}</a>@endif
  <span class="page-number">{{ $locale === 'fa' ? Persian::digits($paginator->currentPage().' / '.$paginator->lastPage()) : $paginator->currentPage().' / '.$paginator->lastPage() }}</span>
  @if ($paginator->nextPageUrl())<a class="page-next" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('site.older') }}</a>@endif
</nav>
@endif
