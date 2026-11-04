@extends('site.layout')
@use('App\Support\Dates')
@use('App\Support\Persian')
@section('main')
<div class="wrap">
  <header class="list-header">
    <h1>{{ __('site.archives') }}</h1>
    <p class="list-count">{{ trans_choice('site.postsCount', $count, ['count' => Persian::forLocale($count, $locale)]) }}</p>
  </header>
  <div class="archive">
    @foreach ($groups as $year => $months)
    <h2 class="archive-year">{{ $year }}</h2>
      @foreach ($months as $month => $posts)
      <h3 class="archive-month">{{ $month }}</h3>
      <ul class="archive-list">
        @foreach ($posts as $post)
        <li><a href="{{ $post->path() }}">{{ $post->title }}</a> <time datetime="{{ $post->published_at->format('Y-m-d') }}">{{ Dates::human($post->published_at, $locale) }}</time></li>
        @endforeach
      </ul>
      @endforeach
    @endforeach
  </div>
</div>
@endsection
