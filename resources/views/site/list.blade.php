@extends('site.layout')
@use('App\Support\Assets')
@use('App\Support\Persian')
@section('main')
<div class="wrap">
  <header class="list-header">
    @isset($parent)
      <nav class="breadcrumbs" aria-label="{{ __('site.breadcrumbs') }}">@if ($parent[1])<a href="{{ $parent[1] }}">{{ $parent[0] }}</a>@else<span>{{ $parent[0] }}</span>@endif</nav>
    @endisset
    <h1>{{ $heading }}</h1>
    <p class="list-count">{{ trans_choice('site.postsCount', $count, ['count' => Persian::forLocale($count, $locale)]) }}</p>
    @isset($feed)
    <p class="list-feed"><a href="{{ $feed }}">{!! Assets::icon('rss') !!}{{ __('site.tagFeed') }}</a></p>
    @endisset
  </header>
  <div class="post-list">
    @foreach ($posts as $post)
      @include('site.partials.post-card', ['post' => $post])
    @endforeach
  </div>
  @include('site.partials.pagination', ['paginator' => $posts])
</div>
@endsection
