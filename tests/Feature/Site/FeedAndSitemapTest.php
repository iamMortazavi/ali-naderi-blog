<?php

namespace Tests\Feature\Site;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;

class FeedAndSitemapTest extends SiteTestCase
{
    public function test_sitemap_lists_both_languages_with_encoded_addresses(): void
    {
        $series = Series::findOrCreateFor('fa', 'سفر');
        Post::query()->where('slug', 'hello')->update(['series_id' => $series->id]);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>https://blog.test/tags/'.rawurlencode('فلسفه').'</loc>', false)
            ->assertSee('<loc>https://blog.test/series/'.rawurlencode('سفر').'</loc>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="https://blog.test/en/posts/hello-en"/>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="x-default" href="https://blog.test/posts/hello"/>', false)
            ->assertSee('<loc>https://blog.test/en/about</loc>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="https://blog.test/en/about"/>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="x-default" href="https://blog.test/about"/>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="https://blog.test/en/tags"/>', false)
            ->assertSee('<lastmod>2026-11-01T10:00:00', false)
            ->assertDontSee('/posts/draft')
            ->assertDontSee('/posts/later');
    }

    public function test_feed_has_absolute_media_and_no_heading_anchors(): void
    {
        Post::create(['locale' => 'fa', 'slug' => 'media', 'title' => 'تصویر', 'description' => 'd',
            'body' => "## بخش\n\n<img src=\"/uploads/a.png\" srcset=\"/uploads/a-480.webp 480w, /uploads/a-960.webp 960w\" alt=\"a\">\n\n[بالا](#بخش)",
            'status' => PostStatus::Published, 'published_at' => now()->subHour()]);

        $content = $this->get('/index.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
            ->assertSee('<atom:link href="https://blog.test/index.xml" rel="self"', false)
            ->assertSee('<link>https://blog.test/posts/hello</link>', false)
            ->assertSee('<lastBuildDate>', false)
            ->getContent();

        $this->assertStringContainsString('srcset="https://blog.test/uploads/a-480.webp 480w, https://blog.test/uploads/a-960.webp 960w"', $content);
        $this->assertStringContainsString('src="https://blog.test/uploads/a.png"', $content);
        $this->assertStringContainsString('href="https://blog.test/posts/media#', $content);
        $this->assertStringNotContainsString('heading-anchor', $content);
    }

    public function test_tag_feed_and_robots(): void
    {
        $this->get('/tags/'.rawurlencode('فلسفه').'/index.xml')->assertOk()
            ->assertSee('<atom:link href="https://blog.test/tags/'.rawurlencode('فلسفه').'/index.xml" rel="self"', false)
            ->assertSee('<link>https://blog.test/posts/hello</link>', false);
        $this->assertNotNull(Tag::query()->first());
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    }
}
