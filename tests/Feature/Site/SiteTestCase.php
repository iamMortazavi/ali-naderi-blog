<?php

namespace Tests\Feature\Site;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A small bilingual site: one translated post, a draft, a scheduled post, a Persian tag and an about page. */
abstract class SiteTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The page cache lives in memory during tests, so tests never share cached pages.
        config(['cache.stores.pages' => ['driver' => 'array', 'serialize' => true]]);
        app('cache')->forgetDriver('pages');

        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ علی', 'title_en' => 'Ali']);
        $hello = Post::create(['locale' => 'fa', 'translation_key' => 'hello', 'slug' => 'hello', 'title' => 'سلام',
            'description' => 'نوشته‌ی اول', 'body' => "## بخش\n\nمتن", 'status' => PostStatus::Published, 'published_at' => '2026-11-01 10:00']);
        Post::create(['locale' => 'en', 'translation_key' => 'hello', 'slug' => 'hello-en', 'title' => 'Hello',
            'body' => 'Text', 'status' => PostStatus::Published, 'published_at' => '2026-11-01 10:00']);
        Post::create(['locale' => 'fa', 'slug' => 'draft', 'title' => 'پیش‌نویس', 'body' => 'x', 'status' => PostStatus::Draft]);
        Post::create(['locale' => 'fa', 'slug' => 'later', 'title' => 'بعدا', 'body' => 'x',
            'status' => PostStatus::Published, 'published_at' => now()->addDay()]);
        $hello->tags()->attach(Tag::findOrCreateFor('fa', 'فلسفه'));
        Page::create(['locale' => 'fa', 'slug' => 'about', 'title' => 'درباره', 'body' => 'من', 'in_sitemap' => true]);
        Page::create(['locale' => 'en', 'slug' => 'about', 'title' => 'About', 'body' => 'Me', 'in_sitemap' => true]);
    }

    /** Add $count live posts in one language, one day apart, older than the setup posts. */
    protected function addPosts(int $count, string $locale = 'fa'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Post::create(['locale' => $locale, 'slug' => "extra-{$i}", 'title' => "Extra {$i}", 'description' => 'd', 'body' => 'b',
                'status' => PostStatus::Published, 'published_at' => now()->subDays(30 + $i)]);
        }
    }
}
