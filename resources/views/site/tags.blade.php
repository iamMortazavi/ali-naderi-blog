@extends('site.layout')
@php use App\Support\Persian; @endphp
@section('main')
<div class="wrap">
  <header class="list-header"><h1>{{ __('site.tags') }}</h1></header>
  <ul class="term-cloud">
    @foreach ($tags as $tag)
    <li><a href="{{ $tag->path() }}">#{{ $tag->name }} <sup>{{ $locale === 'fa' ? Persian::digits($tag->posts_count) : $tag->posts_count }}</sup></a></li>
    @endforeach
  </ul>
</div>
@endsection
