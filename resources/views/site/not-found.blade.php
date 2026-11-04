@extends('site.layout')
@use('App\Support\Locales')
@use('App\Support\Persian')
@section('main')
<div class="wrap not-found">
  <h1>{{ Persian::forLocale('404', $locale) }}</h1>
  <p>{{ __('site.notFound') }}</p>
  <p><a href="{{ Locales::path($locale) }}">{{ __('site.backHome') }}</a></p>
</div>
@endsection
