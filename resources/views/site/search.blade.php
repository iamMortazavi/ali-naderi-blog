@extends('site.layout')
@use('App\Support\Assets')
@use('App\Support\Locales')
@use('App\Support\Persian')
@section('main')
<div class="wrap">
  <header class="list-header"><h1>{{ __('site.search') }}</h1></header>
  <form class="search-form" role="search" action="{{ Locales::path($locale, '/search') }}" method="get">
    <label class="visually-hidden" for="q">{{ __('site.searchPlaceholder') }}</label>
    <span class="search-icon">{!! Assets::icon('search') !!}</span>
    <input id="q" name="q" type="search" value="{{ $q }}" autocomplete="off" placeholder="{{ __('site.searchPlaceholder') }}" @if (! $q) autofocus @endif>
    <button type="submit">{{ __('site.searchButton') }}</button>
  </form>
  @if ($searched)
    {{-- Only the count gets Persian digits; the visitor's words are escaped and shown exactly as typed. --}}
    <p class="search-hint">{!! trans_choice('site.searchResults', $posts->count(), ['count' => e(Persian::forLocale($posts->count(), $locale)), 'q' => '<bdi>'.e($q).'</bdi>']) !!}</p>
    <div class="post-list">
      @forelse ($posts as $post)
        @include('site.partials.post-card', ['post' => $post])
      @empty
        <p class="search-hint">{{ __('site.searchEmpty') }}</p>
      @endforelse
    </div>
  @endif
</div>
@endsection
