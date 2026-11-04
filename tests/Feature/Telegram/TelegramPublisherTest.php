<?php

namespace Tests\Feature\Telegram;

use App\Enums\PostStatus;
use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\Tag;
use App\Models\TelegramMessage;
use App\Services\Telegram\TelegramPublisher;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TelegramPublisherTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:SECRET-TOKEN';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => self::TOKEN, 'services.telegram.chat_id' => '@channel']);
        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ', 'title_en' => 'Blog']);
        SiteSettings::put('telegram', ['languages' => ['fa'], 'hashtags' => true, 'footer_fa' => '@channel']);
    }

    private function fakeOk(): void
    {
        $id = 100;
        Http::fake(['api.telegram.org/*' => function () use (&$id) {
            return Http::response(['ok' => true, 'result' => ['message_id' => ++$id]]);
        }]);
    }

    private function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($attributes + [
            'locale' => 'fa', 'slug' => "post-{$n}", 'title' => "عنوان {$n}", 'description' => 'خلاصه',
            'body' => 'متن', 'status' => PostStatus::Published, 'published_at' => now()->subHour(),
        ]);
    }

    private function publisher(): TelegramPublisher
    {
        return app(TelegramPublisher::class);
    }

    public function test_sends_each_live_post_once(): void
    {
        $this->fakeOk();
        $post = $this->makePost();
        $post->tags()->attach(Tag::findOrCreateFor('fa', 'هوش مصنوعی'));

        $rows = $this->publisher()->sendDue(pause: 0);
        $this->assertCount(1, $rows);
        $this->assertSame(TelegramStatus::Sent, $rows[0]->status);
        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));

        $message = $post->fresh()->telegramMessage;
        $this->assertSame(TelegramStatus::Sent, $message->status);
        $this->assertSame(101, $message->message_id);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->sent_at);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return str_ends_with($request->url(), '/sendMessage') && $request['parse_mode'] === 'HTML'
                && str_starts_with($text, '<b>عنوان ')
                && str_contains($text, '#هوش_مصنوعی') && str_contains($text, '>ادامهٔ مطلب</a>')
                && str_contains($text, 'href="https://blog.test/posts/') && str_ends_with($text, '@channel');
        });
    }

    public function test_english_posts_use_the_english_read_more(): void
    {
        $this->fakeOk();
        SiteSettings::put('telegram', ['languages' => ['en']]);
        $this->makePost(['locale' => 'en', 'title' => 'Hello']);

        $this->publisher()->sendDue(pause: 0);

        Http::assertSent(fn (Request $r) => str_contains($r['text'], '>Read more</a>') && str_contains($r['text'], 'https://blog.test/en/posts/'));
    }

    public function test_skips_drafts_future_disabled_other_languages_and_old_posts(): void
    {
        $this->fakeOk();
        $this->makePost(['status' => PostStatus::Draft]);
        $this->makePost(['published_at' => now()->addDay()]);
        $this->makePost(['send_to_telegram' => false]);
        $this->makePost(['locale' => 'en']);
        $this->makePost(['published_at' => now()->subHours(49)]);

        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));
        Http::assertNothingSent();
    }

    public function test_max_age_window_comes_from_config(): void
    {
        $this->fakeOk();
        $inside = $this->makePost(['published_at' => now()->subHours(47)]);
        $outside = $this->makePost(['published_at' => now()->subHours(49)]);

        $this->assertSame([$inside->id], $this->publisher()->due()->pluck('id')->all());

        config(['blog.telegram.max_age_hours' => 72]);
        $this->assertEqualsCanonicalizing([$inside->id, $outside->id], $this->publisher()->due()->pluck('id')->all());
    }

    public function test_an_existing_claim_prevents_a_second_send(): void
    {
        $this->fakeOk();
        $post = $this->makePost();
        $post->telegramMessage()->create(['chat_id' => '@channel', 'status' => TelegramStatus::Sending]);

        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));
        Http::assertNothingSent();
    }

    public function test_a_concurrent_claim_wins_and_this_run_skips_the_post(): void
    {
        $this->fakeOk();
        $post = $this->makePost();
        // Another process claims the post between due() and the insert of this run.
        TelegramMessage::creating(function (TelegramMessage $row) use ($post) {
            if (! DB::table('telegram_messages')->where('post_id', $post->id)->exists()) {
                DB::table('telegram_messages')->insert(['post_id' => $post->id, 'chat_id' => '@other', 'status' => 'sending',
                    'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));
        Http::assertNothingSent();
        $this->assertSame('@other', TelegramMessage::query()->sole()->chat_id);
    }

    public function test_a_refused_post_does_not_stop_the_others(): void
    {
        $bad = $this->makePost(['title' => 'BAD']);
        $good = $this->makePost(['title' => 'GOOD']);
        Http::fake(fn (Request $r) => str_contains($r['text'], 'BAD')
            ? Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message is too long'], 400)
            : Http::response(['ok' => true, 'result' => ['message_id' => 7]]));

        $rows = $this->publisher()->sendDue(pause: 0);

        $this->assertCount(2, $rows);
        $failed = $bad->fresh()->telegramMessage;
        $this->assertSame(TelegramStatus::Failed, $failed->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertStringContainsString('message is too long', $failed->last_error);
        $this->assertSame(TelegramStatus::Sent, $good->fresh()->telegramMessage->status);
    }

    public function test_a_refused_post_is_retried_ten_minutes_later_up_to_max_attempts(): void
    {
        $post = $this->makePost();
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot is not a member'], 403)]);

        $this->publisher()->sendDue(pause: 0);
        $this->assertCount(0, $this->publisher()->sendDue(pause: 0), 'not again within 10 minutes');

        $this->travel(11)->minutes();
        $this->publisher()->sendDue(pause: 0);
        $this->travel(11)->minutes();
        $this->publisher()->sendDue(pause: 0);
        $this->assertSame(3, $post->fresh()->telegramMessage->attempts);

        $this->travel(11)->minutes();
        $this->assertCount(0, $this->publisher()->sendDue(pause: 0), 'max_attempts reached');
        Http::assertSentCount(3);
    }

    public function test_a_photo_refused_with_400_falls_back_to_text_once(): void
    {
        $post = $this->makePost(['cover_path' => 'covers/a.png', 'description' => str_repeat('و', 3000)]);
        Http::fake([
            '*/sendPhoto' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: wrong file identifier/HTTP URL specified'], 400),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $this->publisher()->sendDue(pause: 0);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sendPhoto')
            && $r['photo'] === 'https://blog.test/uploads/covers/a.png'
            && mb_strlen($r['caption']) <= TelegramPublisher::CAPTION_LIMIT);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage') && mb_strlen($r['text']) <= TelegramPublisher::MESSAGE_LIMIT);
        $this->assertSame(TelegramStatus::Sent, $post->fresh()->telegramMessage->status);
        $this->assertSame(9, $post->fresh()->telegramMessage->message_id);
    }

    public function test_a_photo_refused_otherwise_is_a_failure_without_fallback(): void
    {
        $post = $this->makePost(['cover_path' => 'covers/a.png']);
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden'], 403)]);

        $this->publisher()->sendDue(pause: 0);

        Http::assertSentCount(1);
        $this->assertSame(TelegramStatus::Failed, $post->fresh()->telegramMessage->status);
    }

    public function test_no_answer_leaves_the_post_unknown_and_never_resends_it(): void
    {
        $post = $this->makePost();
        Http::fake(fn () => Http::failedConnection('cURL error 28: timed out for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));

        $rows = $this->publisher()->sendDue(pause: 0);

        $this->assertSame(TelegramStatus::Sending, $rows[0]->status);
        $message = $post->fresh()->telegramMessage;
        $this->assertSame(TelegramStatus::Sending, $message->status);
        $this->assertStringContainsString('timed out', $message->last_error);
        $this->assertStringNotContainsString('SECRET', $message->last_error);

        $this->travel(2)->hours();
        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));
        Http::assertSentCount(1);
    }

    public function test_a_non_json_answer_is_unknown_too(): void
    {
        $post = $this->makePost();
        Http::fake(['*' => Http::response('<html>502 Bad Gateway</html>', 502)]);

        $this->publisher()->sendDue(pause: 0);

        $this->assertSame(TelegramStatus::Sending, $post->fresh()->telegramMessage->status);
    }

    public function test_manual_send_retry_and_safe_errors(): void
    {
        $post = $this->makePost(['published_at' => now()->subYear()]);
        Http::fake(fn () => throw new RuntimeException('cURL error for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));

        try {
            $this->publisher()->send($post);
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
        $this->assertSame(TelegramStatus::Sending, $post->fresh()->telegramMessage->status);

        $this->expectExceptionMessage('may already be in the channel');
        $this->publisher()->send($post->fresh());
    }

    public function test_retry_replaces_an_unknown_or_failed_attempt(): void
    {
        $this->fakeOk();
        $post = $this->makePost(['published_at' => now()->subYear()]);
        $post->telegramMessage()->create(['chat_id' => '@channel', 'status' => TelegramStatus::Sending, 'attempts' => 1, 'last_error' => 'timeout']);

        $message = $this->publisher()->retry($post);

        $this->assertSame(TelegramStatus::Sent, $message->status);
        $this->assertSame(1, TelegramMessage::query()->count());
        $this->assertNull($message->last_error);

        $this->expectExceptionMessage('already sent');
        $this->publisher()->retry($post->fresh());
    }

    public function test_manual_send_of_a_skipped_post(): void
    {
        $this->fakeOk();
        $post = $this->makePost();
        $post->telegramMessage()->create(['chat_id' => '@channel', 'status' => TelegramStatus::Skipped]);

        $this->assertSame(TelegramStatus::Sent, $this->publisher()->send($post)->status);
        Http::assertSentCount(1);
    }

    public function test_manual_send_needs_a_configured_bot(): void
    {
        config(['services.telegram.bot_token' => null]);

        $this->assertFalse($this->publisher()->configured());
        $this->assertSame([], $this->publisher()->sendDue(pause: 0));
        $this->expectExceptionMessage('not set up');
        $this->publisher()->send($this->makePost());
    }

    public function test_baseline_marks_due_posts_as_skipped_without_sending(): void
    {
        $this->fakeOk();
        $this->makePost();
        $this->makePost();
        $failed = $this->makePost();
        $failed->telegramMessage()->create(['chat_id' => '@channel', 'status' => TelegramStatus::Failed, 'attempts' => 1]);
        $this->travel(11)->minutes();

        $this->assertSame(3, $this->publisher()->baseline());
        $this->assertSame(3, TelegramMessage::query()->where('status', TelegramStatus::Skipped)->count());
        $this->assertCount(0, $this->publisher()->sendDue(pause: 0));
        Http::assertNothingSent();
    }

    public function test_the_token_never_reaches_logs(): void
    {
        config(['services.telegram.notify_chat_id' => '42']);
        Http::fake(fn () => throw new RuntimeException('failed https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));
        Log::spy();

        $this->publisher()->notify('hello');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => ! str_contains($message, 'SECRET') && str_contains($message, '***'));
    }

    public function test_hashtags(): void
    {
        $this->assertSame('#یادگیری_ماشین', TelegramPublisher::hashtag('یادگیری ماشین'));
        $this->assertSame('#نیم_فاصله', TelegramPublisher::hashtag("نیم\u{200C}فاصله"));
        $this->assertSame('#open_source', TelegramPublisher::hashtag('open-source'));
        $this->assertSame('#C_Go', TelegramPublisher::hashtag('C++ & Go'));
        $this->assertSame('#a_b', TelegramPublisher::hashtag('<a>"b"'));
        $this->assertSame('', TelegramPublisher::hashtag('!!!'));
    }

    public function test_hashtags_in_the_message_are_safe_html(): void
    {
        $this->fakeOk();
        $post = $this->makePost();
        $post->tags()->attach(Tag::findOrCreateFor('fa', 'a<b>&c'));
        $post->tags()->attach(Tag::findOrCreateFor('fa', '***'));

        $text = $this->publisher()->compose($post->fresh(), TelegramPublisher::MESSAGE_LIMIT);

        $this->assertStringContainsString('#a_b_c', $text);
        $this->assertStringNotContainsString('<b>&', $text);
        $this->assertStringNotContainsString('# ', $text);
    }
}
