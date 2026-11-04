@extends('site.layout')
@php
    use App\Support\Assets;
    use App\Support\Locales;
    use App\Support\SiteSettings;
@endphp
@section('main')
<div class="wrap">
  @if ($posts->onFirstPage())
  <section class="profile">
    <h1 class="profile-title">{{ SiteSettings::title($locale) }}</h1>
    @if ($bio = SiteSettings::get('author.bio_'.$locale))
    <p class="profile-bio">{{ $bio }}</p>
    @endif
    <ul class="social">
      @foreach (SiteSettings::get('social.links', []) as $link)
      <li><a href="{{ $link['url'] }}" rel="me noopener" aria-label="{{ $link['name'] }}" title="{{ $link['name'] }}">{!! Assets::icon($link['name']) !!}</a></li>
      @endforeach
      <li><a href="{{ Locales::path($locale, '/index.xml') }}" aria-label="RSS" title="RSS">{!! Assets::icon('rss') !!}</a></li>
    </ul>
  </section>
  @endif
  <div class="post-list">
    @foreach ($posts as $post)
      @include('site.partials.post-card', ['post' => $post])
    @endforeach
  </div>
  @include('site.partials.pagination', ['paginator' => $posts])
</div>
@endsection
