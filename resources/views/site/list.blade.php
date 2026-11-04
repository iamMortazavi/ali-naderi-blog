@extends('site.layout')
@php use App\Support\Persian; @endphp
@section('main')
<div class="wrap">
  <header class="list-header">
    @isset($parent)
      <nav class="breadcrumbs">@if ($parent[1])<a href="{{ $parent[1] }}">{{ $parent[0] }}</a>@else<span>{{ $parent[0] }}</span>@endif</nav>
    @endisset
    <h1>{{ $heading }}</h1>
    <p class="list-count">{{ $locale === 'fa' ? Persian::digits(__('site.postsCount', ['count' => $count])) : __('site.postsCount', ['count' => $count]) }}</p>
  </header>
  <div class="post-list">
    @foreach ($posts as $post)
      @include('site.partials.post-card', ['post' => $post])
    @endforeach
  </div>
  @include('site.partials.pagination', ['paginator' => $posts])
</div>
@endsection
