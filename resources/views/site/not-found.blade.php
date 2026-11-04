@extends('site.layout')
@php use App\Support\Locales; @endphp
@section('main')
<div class="wrap not-found">
  <h1>{{ $locale === 'fa' ? '۴۰۴' : '404' }}</h1>
  <p>{{ __('site.notFound') }}</p>
  <p><a href="{{ Locales::path($locale) }}">{{ __('site.backHome') }}</a></p>
</div>
@endsection
