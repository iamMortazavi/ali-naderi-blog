<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ علی', 'title_en' => 'Ali']);
        Post::create(['locale' => 'fa', 'translation_key' => 'hello', 'slug' => 'hello', 'title' => 'سلام',
            'description' => 'نوشته‌ی اول', 'body' => "## بخش\n\nمتن", 'status' => PostStatus::Published, 'published_at' => '2026-11-01 10:00']);
        Post::create(['locale' => 'en', 'translation_key' => 'hello', 'slug' => 'hello-en', 'title' => 'Hello',
            'body' => 'Text', 'status' => PostStatus::Published, 'published_at' => '2026-11-01 10:00']);
        Post::create(['locale' => 'fa', 'slug' => 'draft', 'title' => 'پیش‌نویس', 'body' => 'x', 'status' => PostStatus::Draft]);
        Post::create(['locale' => 'fa', 'slug' => 'later', 'title' => 'بعدا', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now()->addDay()]);
        Page::create(['locale' => 'fa', 'slug' => 'about', 'title' => 'درباره', 'body' => 'من']);
    }

    public function test_home_lists_only_live_posts_in_its_language(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<html lang="fa" dir="rtl"', false)
            ->assertSee('سلام')
            ->assertDontSee('پیش‌نویس')
            ->assertDontSee('بعدا')
            ->assertDontSee('Hello');
        $this->get('/en')->assertOk()->assertSee('<html lang="en" dir="ltr"', false)->assertSee('Hello');
    }

    public function test_post_page_has_seo_and_structured_data(): void
    {
        $response = $this->get('/posts/hello')->assertOk()
            ->assertSee('<link rel="canonical" href="https://blog.test/posts/hello">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="https://blog.test/en/posts/hello-en">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="https://blog.test/posts/hello">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('۱۰ آبان ۱۴۰۵');

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $m);
        $graph = collect(json_decode($m[1], true)['@graph']);
        $post = $graph->firstWhere('@type', 'BlogPosting');
        $this->assertSame('https://blog.test/posts/hello', $post['url']);
        $this->assertSame('fa-IR', $post['inLanguage']);
        $this->assertNotNull($graph->firstWhere('@type', 'BreadcrumbList'));
    }

    public function test_drafts_and_scheduled_posts_are_hidden(): void
    {
        $this->get('/posts/draft')->assertNotFound();
        $this->get('/posts/later')->assertNotFound();
    }

    public function test_slug_change_redirects_the_old_address(): void
    {
        Post::query()->where('slug', 'hello')->first()->update(['slug' => 'hello-world']);

        $this->get('/posts/hello')->assertRedirect('/posts/hello-world')->assertStatus(301);
    }

    public function test_lists_search_pages_feeds_and_sitemap(): void
    {
        $this->get('/tags')->assertOk();
        $this->get('/archives')->assertOk()->assertSee('آبان');
        $this->get('/search?q='.urlencode('سلام'))->assertOk()->assertSee('href="/posts/hello"', false);
        $this->get('/search?q='.urlencode('سلام'))->assertSee('۱ نتیجه');
        $this->get('/search?q='.urlencode('ي'))->assertOk();
        $this->get('/about')->assertOk()->assertSee('درباره');
        $this->get('/index.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
            ->assertSee('<link>https://blog.test/posts/hello</link>', false);
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="https://blog.test/en/posts/hello-en"/>', false)
            ->assertDontSee('/posts/draft');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
        $this->get('/no-such-page')->assertNotFound()->assertSee('۴۰۴');
    }
}
