@extends('site.layout')
@use('App\Support\Persian')
@section('main')
<div class="wrap">
  <header class="list-header"><h1>{{ __('site.tags') }}</h1></header>
  <ul class="term-cloud">
    @foreach ($tags as $tag)
    <li><a href="{{ $tag->path() }}">#{{ $tag->name }} <sup>{{ Persian::forLocale($tag->posts_count, $locale) }}</sup></a></li>
    @endforeach
  </ul>
</div>
@endsection
