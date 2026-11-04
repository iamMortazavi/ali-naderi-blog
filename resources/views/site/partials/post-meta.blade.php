@use('App\Support\Assets')
@use('App\Support\Dates')
@use('App\Support\Persian')
@use('App\Support\SiteSettings')
<span class="meta-item"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ Dates::human($post->published_at, $locale) }}</time></span>
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ trans_choice('site.readingTime', $post->reading_minutes, ['count' => Persian::forLocale($post->reading_minutes, $locale)]) }}</span>
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ trans_choice('site.words', $post->word_count, ['count' => Persian::forLocale($post->word_count, $locale)]) }}</span>
@if ($author = SiteSettings::authorName($locale) ?: $post->author?->name)
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ $author }}</span>
@endif
@if ($post->audio_url)
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item meta-audio" title="{{ __('site.hasAudio') }}">{!! Assets::icon('headphones') !!}<span class="visually-hidden">{{ __('site.hasAudio') }}</span></span>
@endif
