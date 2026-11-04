<?php

namespace Tests\Feature\Console;

use App\Console\Commands\PublishDue;
use App\Enums\PostStatus;
use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Support\PageCache;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishDueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A store that serializes, with object unserialization off like production (config/cache.php).
        config([
            'cache.default' => 'database',
            'cache.serializable_classes' => false,
            'cache.stores.pages' => ['driver' => 'array'],
        ]);
        Cache::forgetDriver(['database', 'pages']);
        config([
            'services.telegram.bot_token' => '123:SECRET', 'services.telegram.chat_id' => '@channel',
            'services.telegram.notify_chat_id' => '42',
        ]);
        SiteSettings::put('site', ['url' => 'https://blog.test']);
        SiteSettings::put('telegram', ['languages' => ['fa']]);
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create($attributes + [
            'locale' => 'fa', 'slug' => 'post-'.uniqid(), 'title' => 'عنوان', 'body' => 'متن',
            'status' => PostStatus::Published, 'published_at' => now()->subMinutes(30),
        ]);
    }

    /** @return int requests sent to the maintainer chat */
    private function notices(): int
    {
        return Http::recorded(fn (Request $r) => $r['chat_id'] === '42')->count();
    }

    public function test_the_store_really_drops_objects(): void
    {
        Cache::forever('probe', now());

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, Cache::get('probe'));
    }

    public function test_the_last_check_is_stored_as_a_timestamp_and_every_run_works(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->artisan('blog:publish-due')->assertSuccessful();
        $this->assertIsInt(Cache::get(PublishDue::LAST_CHECK_KEY));
        $this->travel(1)->minutes();
        $this->artisan('blog:publish-due')->assertSuccessful();
        $this->assertSame(now()->getTimestamp(), Cache::get(PublishDue::LAST_CHECK_KEY));
    }

    public function test_page_cache_is_flushed_when_a_scheduled_post_goes_live(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->makePost(['published_at' => now()->addSeconds(30), 'send_to_telegram' => false]);
        $this->artisan('blog:publish-due')->assertSuccessful();

        PageCache::store()->put('page:x', 'html');
        $this->travel(1)->minutes();
        $this->artisan('blog:publish-due')->assertSuccessful();
        $this->assertNull(PageCache::store()->get('page:x'), 'the post went live since the last check');

        PageCache::store()->put('page:x', 'html');
        $this->travel(1)->minutes();
        $this->artisan('blog:publish-due')->assertSuccessful();
        $this->assertSame('html', PageCache::store()->get('page:x'), 'nothing new went live');
    }

    public function test_success_is_announced_once(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $post = $this->makePost();

        $this->artisan('blog:publish-due')->expectsOutputToContain("sent fa/{$post->slug}")->assertSuccessful();
        $this->artisan('blog:publish-due')->assertSuccessful();

        $this->assertSame(1, $this->notices());
    }

    public function test_a_failure_is_announced_once_not_on_every_retry(): void
    {
        Http::fake(fn (Request $r) => $r['chat_id'] === '42'
            ? Http::response(['ok' => true, 'result' => ['message_id' => 1]])
            : Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400));
        $post = $this->makePost();

        $this->artisan('blog:publish-due')->assertFailed();
        $this->assertSame(1, $this->notices());
        $this->travel(11)->minutes();
        $this->artisan('blog:publish-due')->assertFailed();
        $this->travel(11)->minutes();
        $this->artisan('blog:publish-due')->assertFailed();

        $this->assertSame(3, $post->fresh()->telegramMessage->attempts);
        $this->assertSame(TelegramStatus::Failed, $post->fresh()->telegramMessage->status);
        $this->assertSame(1, $this->notices());
        Http::assertSent(fn (Request $r) => $r['chat_id'] === '42' && str_contains($r['text'], 'chat not found')
            && ! str_contains($r['text'], 'SECRET'));
    }

    public function test_an_unknown_result_is_announced_once(): void
    {
        Http::fake(fn (Request $r) => $r['chat_id'] === '42'
            ? Http::response(['ok' => true, 'result' => ['message_id' => 1]])
            : Http::failedConnection('timed out'));
        $this->makePost();

        $this->artisan('blog:publish-due')->assertFailed();
        $this->travel(1)->hours();
        $this->artisan('blog:publish-due')->assertSuccessful();

        $this->assertSame(1, $this->notices());
    }

    public function test_baseline_option(): void
    {
        Http::fake();
        $this->makePost();

        $this->artisan('blog:publish-due --baseline')->expectsOutputToContain('1 post(s) marked as sent.')->assertSuccessful();
        Http::assertNothingSent();
    }
}
