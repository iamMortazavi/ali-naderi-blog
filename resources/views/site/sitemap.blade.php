{!! '<?xml version="1.0" encoding="utf-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
  <url>
    <loc>{{ $entry['loc'] }}</loc>
    @if ($entry['lastmod'])<lastmod>{{ \Illuminate\Support\Carbon::parse($entry['lastmod'])->toAtomString() }}</lastmod>@endif
    @foreach ($entry['alternates'] as $code => $url)
    <xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}"/>
    @endforeach
  </url>
@endforeach
</urlset>
