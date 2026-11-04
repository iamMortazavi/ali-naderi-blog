<?php

namespace Tests\Feature\Site;

class PublicSiteTest extends SiteTestCase
{
    public function test_home_lists_only_live_posts_in_its_language(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<html lang="fa" dir="rtl"', false)
            ->assertSee('سلام')
            ->assertSee('<a href="/posts">همه‌ی نوشته‌ها</a>', false)
            ->assertDontSee('پیش‌نویس')
            ->assertDontSee('بعدا')
            ->assertDontSee('Hello');
        $this->get('/en')->assertOk()->assertSee('<html lang="en" dir="ltr"', false)->assertSee('Hello')
            ->assertSee('<a href="/en/posts">All posts</a>', false);
    }

    public function test_post_page_has_seo_and_structured_data(): void
    {
        $response = $this->get('/posts/hello')->assertOk()
            ->assertSee('<link rel="canonical" href="https://blog.test/posts/hello">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="https://blog.test/en/posts/hello-en">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="https://blog.test/posts/hello">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:locale:alternate" content="en_US">', false)
            ->assertSee('<meta name="twitter:image:alt"', false)
            ->assertSee('<nav class="breadcrumbs" aria-label="مسیر صفحه">', false)
            ->assertSee('href="https://blog.test/en/posts/hello-en" hreflang="en"', false)
            ->assertSee('۱۰ آبان ۱۴۰۵');

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $m);
        $graph = collect(json_decode($m[1], true)['@graph']);
        $post = $graph->firstWhere('@type', 'BlogPosting');
        $this->assertSame('https://blog.test/posts/hello', $post['url']);
        $this->assertSame('fa-IR', $post['inLanguage']);
        $this->assertSame('https://blog.test/#author', $post['author']['@id']);
        $this->assertNotNull($graph->firstWhere('@type', 'BreadcrumbList'));
    }

    public function test_english_pages_use_english_person_id_and_language(): void
    {
        $response = $this->get('/en/posts/hello-en')->assertOk();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $m);
        $post = collect(json_decode($m[1], true)['@graph'])->firstWhere('@type', 'BlogPosting');
        $this->assertSame('en-US', $post['inLanguage']);
        $this->assertSame('https://blog.test/#author', $post['author']['@id']);
    }

    public function test_drafts_and_scheduled_posts_are_hidden(): void
    {
        $this->get('/posts/draft')->assertNotFound();
        $this->get('/posts/later')->assertNotFound();
    }

    public function test_not_found_page_has_no_canonical_or_json_ld(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('۴۰۴')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);
        $this->get('/en/no-such-page')->assertNotFound()->assertSee('<html lang="en"', false)->assertSee('404');
    }

    public function test_reserved_segments_are_not_pages(): void
    {
        $this->get('/about')->assertOk()->assertSee('درباره');
        $this->get('/en/about')->assertOk()
            ->assertSee('<link rel="alternate" hreflang="fa" href="https://blog.test/about">', false);
        $this->get('/vendor')->assertNotFound();
        $this->get('/storage')->assertNotFound();
    }

    public function test_accessibility_labels_and_copy_labels_come_from_lang_files(): void
    {
        $this->get('/posts/hello')
            ->assertSee('aria-label="منوی اصلی"', false)
            ->assertSee('aria-label="بازگشت به بالا"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('data-label-copy="کپی" data-label-copied="کپی شد"', false)
            ->assertSee('aria-live="polite"', false);
        $this->get('/en')
            ->assertSee('aria-label="Main menu"', false)
            ->assertSee('aria-label="Back to top"', false);
    }

    public function test_english_counts_use_plurals(): void
    {
        $this->get('/en/posts')->assertOk()->assertSee('1 post')->assertDontSee('1 posts');
        $this->addPosts(2, 'en');
        $this->get('/en/archives')->assertOk()->assertSee('3 posts');
        $this->get('/archives')->assertOk()->assertSee('آبان')->assertSee('۱ نوشته');
    }

    public function test_search_converts_only_the_count(): void
    {
        $this->get('/search?q='.urlencode('سلام'))->assertOk()
            ->assertSee('href="/posts/hello"', false)
            ->assertSee('۱ نتیجه برای «<bdi>سلام</bdi>»', false);
        $this->get('/search?q='.urlencode('php 8'))->assertOk()
            ->assertSee('۰ نتیجه برای «<bdi>php 8</bdi>»', false)
            ->assertDontSee('php ۸');
        $this->get('/search?q='.urlencode('<b>x</b>'))->assertOk()
            ->assertSee('<bdi>&lt;b&gt;x&lt;/b&gt;</bdi>', false);
        $this->get('/en/search?q=hello')->assertOk()->assertSee('1 result for “<bdi>hello</bdi>”', false);
        $this->get('/search?q[]=x')->assertOk();
        $this->get('/search?q='.urlencode('ي'))->assertOk();
    }
}
