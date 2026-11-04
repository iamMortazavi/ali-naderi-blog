{!! '<?xml version="1.0" encoding="utf-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>{{ $title }}</title>
    <link>{{ $link }}</link>
    <description>{{ App\Support\SiteSettings::get('author.bio_'.$locale) }}</description>
    <language>{{ $locale === 'fa' ? 'fa-IR' : 'en-US' }}</language>
    <atom:link href="{{ $self }}" rel="self" type="application/rss+xml"/>
    @if ($posts->isNotEmpty())<lastBuildDate>{{ $posts->first()->published_at->toRfc2822String() }}</lastBuildDate>@endif
    @foreach ($posts as $post)
    <item>
      <title>{{ $post->title }}</title>
      <link>{{ $post->url() }}</link>
      <guid isPermaLink="true">{{ $post->url() }}</guid>
      <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
      @if ($name = App\Support\SiteSettings::authorName($locale))<dc:creator>{{ $name }}</dc:creator>@endif
      @foreach ($post->tags as $tag)<category>{{ $tag->name }}</category>@endforeach
      <description>{{ $post->summary() }}</description>
      <content:encoded><![CDATA[{!! str_replace(['src="/', 'href="/'], ['src="'.App\Support\SiteSettings::url().'/', 'href="'.App\Support\SiteSettings::url().'/'], str_replace(']]>', ']]]]><![CDATA[>', $post->body_html)) !!}]]></content:encoded>
    </item>
    @endforeach
  </channel>
</rss>
