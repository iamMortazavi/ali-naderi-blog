@php
    use App\Support\Assets;
    use App\Support\Dates;
    use App\Support\Persian;
    use App\Support\SiteSettings;
    $num = fn ($text) => $locale === 'fa' ? Persian::digits($text) : $text;
    $author = SiteSettings::authorName($locale) ?: $post->author?->name;
@endphp
<span class="meta-item"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ Dates::human($post->published_at, $locale) }}</time></span>
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ $num(__('site.readingTime', ['count' => $post->reading_minutes])) }}</span>
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ $num(__('site.words', ['count' => $post->word_count])) }}</span>
@if ($author)
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item">{{ $author }}</span>
@endif
@if ($post->audio_url)
<span class="meta-sep" aria-hidden="true">·</span>
<span class="meta-item meta-audio" title="{{ __('site.listen') }}">{!! Assets::icon('headphones') !!}</span>
@endif
