@extends('site.layout')
@php
    use App\Support\Dates;
    use App\Support\Persian;
@endphp
@section('main')
<div class="wrap">
  <header class="list-header">
    <h1>{{ __('site.archives') }}</h1>
    <p class="list-count">{{ $locale === 'fa' ? Persian::digits(__('site.postsCount', ['count' => $count])) : __('site.postsCount', ['count' => $count]) }}</p>
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
