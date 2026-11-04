@use('App\Support\Locales')
{!! '<?xml version="1.0" encoding="utf-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>{{ $title }}</title>
    <link>{{ $link }}</link>
    <description>{{ $description }}</description>
    <language>{{ Locales::tag($locale) }}</language>
    <atom:link href="{{ $self }}" rel="self" type="application/rss+xml"/>
    @if ($updated)<lastBuildDate>{{ $updated->format(DATE_RSS) }}</lastBuildDate>@endif
    @foreach ($posts as $post)
    <item>
      <title>{{ $post->title }}</title>
      <link>{{ $post->url() }}</link>
      <guid isPermaLink="true">{{ $post->url() }}</guid>
      <pubDate>{{ $post->published_at->format(DATE_RSS) }}</pubDate>
      @if ($author)<dc:creator>{{ $author }}</dc:creator>@endif
      @foreach ($post->tags as $tag)<category>{{ $tag->name }}</category>@endforeach
      <description>{{ $post->summary() }}</description>
      <content:encoded><![CDATA[{!! $post->feedHtml() !!}]]></content:encoded>
    </item>
    @endforeach
  </channel>
</rss>
