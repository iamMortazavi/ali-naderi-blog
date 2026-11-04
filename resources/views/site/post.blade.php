@extends('site.layout')
@php
    use App\Support\Assets;
    use App\Support\Dates;
    use App\Support\Locales;
    $toc = collect($post->toc ?? []);
    $video = null;
    if ($post->video_url) {
        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([\w-]{11})~', $post->video_url, $m)) {
            $video = 'https://www.youtube-nocookie.com/embed/'.$m[1];
        } elseif (preg_match('~aparat\.com/v/(\w+)~', $post->video_url, $m)) {
            $video = 'https://www.aparat.com/video/video/embed/videohash/'.$m[1].'/vt/frame';
        }
    }
@endphp
@section('main')
<article class="post wrap">
  <header class="post-header">
    <nav class="breadcrumbs" aria-label="breadcrumbs">
      <a href="{{ Locales::path($locale) }}">{{ __('site.home') }}</a><span aria-hidden="true">›</span>
      <a href="{{ Locales::path($locale, '/posts') }}">{{ __('site.posts') }}</a>
    </nav>
    <h1 class="post-title">{{ $post->title }}</h1>
    @if ($post->description)
    <p class="post-description">{{ $post->description }}</p>
    @endif
    <div class="post-meta">
      @include('site.partials.post-meta', ['post' => $post])
      @if ($post->content_updated_at && $post->content_updated_at->gt($post->published_at->copy()->addDay()))
      <span class="meta-sep" aria-hidden="true">·</span>
      <span class="meta-item">{{ __('site.updated') }} <time datetime="{{ $post->content_updated_at->toIso8601String() }}">{{ Dates::human($post->content_updated_at, $locale) }}</time></span>
      @endif
    </div>
    @if ($translations->isNotEmpty())
    <p class="translations">{{ __('site.readIn') }}:
      @foreach ($translations as $t) <a href="{{ $t->path() }}" hreflang="{{ $t->locale }}" lang="{{ $t->locale }}">{{ Locales::label($t->locale) }}</a>@endforeach
    </p>
    @endif
  </header>

  @if ($cover = $post->coverUrl())
  <figure class="post-cover">
    @php $variants = (! str_starts_with($post->cover_path, 'http')) ? app(App\Services\Content\ImageVariants::class)->for($post->cover_path) : null; @endphp
    <img src="{{ $variants['src'] ?? $cover }}" @if ($variants) srcset="{{ $variants['srcset'] }}" sizes="(max-width: 760px) 100vw, 720px" width="{{ $variants['width'] }}" height="{{ $variants['height'] }}" @endif alt="{{ $post->cover_alt }}" fetchpriority="high" decoding="async">
  </figure>
  @endif

  @if ($audio = $post->audioSource())
  <section class="media media-audio" aria-label="{{ __('site.listen') }}">
    <p class="media-label">{!! Assets::icon('headphones') !!} {{ __('site.listen') }}</p>
    <audio controls preload="none" src="{{ $audio }}"></audio>
  </section>
  @endif
  @if ($post->video_url)
  <section class="media media-video" aria-label="{{ __('site.watch') }}">
    @if ($video)
    <div class="video-frame"><iframe src="{{ $video }}" title="{{ $post->title }}" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    @else
    <video controls preload="none" src="{{ $post->video_url }}"></video>
    @endif
  </section>
  @endif

  @if ($post->show_toc && $toc->count() >= 3)
  <details class="toc" open>
    <summary>{{ __('site.toc') }}</summary>
    <ul>
      @foreach ($toc as $h)
      <li class="toc-level-{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>
      @endforeach
    </ul>
  </details>
  @endif

  <div class="post-content">
    {!! $post->body_html !!}
  </div>

  <footer class="post-footer">
    @if ($post->series)
    <p class="post-series">{{ __('site.series') }}: <a href="{{ $post->series->path() }}">{{ $post->series->name }}</a></p>
    @endif
    @if ($post->tags->isNotEmpty())
    <ul class="tags" aria-label="{{ __('site.tags') }}">
      @foreach ($post->tags as $tag)<li><a href="{{ $tag->path() }}">#{{ $tag->name }}</a></li>@endforeach
    </ul>
    @endif

    <div class="share">
      <span>{{ __('site.share') }}:</span>
      <a href="https://t.me/share/url?url={{ urlencode($post->url()) }}&text={{ urlencode($post->title) }}" rel="noopener" target="_blank" aria-label="Telegram">{!! Assets::icon('telegram') !!}</a>
      <a href="https://x.com/intent/post?url={{ urlencode($post->url()) }}&text={{ urlencode($post->title) }}" rel="noopener" target="_blank" aria-label="X">{!! Assets::icon('x') !!}</a>
      <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($post->url()) }}" rel="noopener" target="_blank" aria-label="LinkedIn">{!! Assets::icon('linkedin') !!}</a>
      <button type="button" class="copy-link" data-url="{{ $post->url() }}" aria-label="{{ __('site.copyLink') }}">{!! Assets::icon('link') !!}</button>
    </div>

    @if ($newer || $older)
    <nav class="post-nav">
      @if ($newer)<a class="post-nav-newer" href="{{ $newer->path() }}" rel="next"><small>{{ __('site.newer') }}</small><span>{{ $newer->title }}</span></a>@endif
      @if ($older)<a class="post-nav-older" href="{{ $older->path() }}" rel="prev"><small>{{ __('site.older') }}</small><span>{{ $older->title }}</span></a>@endif
    </nav>
    @endif

    @if ($related->isNotEmpty())
    <section class="related">
      <h2>{{ __('site.related') }}</h2>
      <ul>
        @foreach ($related as $r)<li><a href="{{ $r->path() }}">{{ $r->title }}</a> <small>{{ Dates::human($r->published_at, $locale) }}</small></li>@endforeach
      </ul>
    </section>
    @endif
  </footer>
</article>
@endsection
