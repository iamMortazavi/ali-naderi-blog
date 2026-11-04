@extends('site.layout')
@section('main')
<article class="page wrap">
  <header class="post-header"><h1 class="post-title">{{ $page->title }}</h1></header>
  <div class="post-content">{!! $page->body_html !!}</div>
</article>
@endsection
