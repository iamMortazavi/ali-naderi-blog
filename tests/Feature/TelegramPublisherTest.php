<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use App\Services\Telegram\TelegramPublisher;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => 'TEST', 'services.telegram.chat_id' => '@channel']);
        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ', 'title_en' => 'Blog']);
        SiteSettings::put('telegram', ['languages' => ['fa'], 'hashtags' => true, 'footer_fa' => '@channel']);
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 101]])
            ->push(['ok' => true, 'result' => ['message_id' => 102]])
            ->push(['ok' => true, 'result' => ['message_id' => 103]])]);
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

    public function test_sends_each_live_post_once(): void
    {
        $post = $this->makePost();
        $post->tags()->attach(Tag::findOrCreateFor('fa', 'هوش مصنوعی'));
        $publisher = app(TelegramPublisher::class);

        $this->assertCount(1, $publisher->sendDue(pause: 0));
        $this->assertCount(0, $publisher->sendDue(pause: 0));
        $this->assertSame(101, $post->fresh()->telegramMessage->message_id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $text = $request['text'];

            return str_ends_with($request->url(), '/sendMessage') && $request['parse_mode'] === 'HTML'
                && str_starts_with($text, '<b>عنوان 1</b>') && str_contains($text, '#هوش_مصنوعی')
                && str_contains($text, 'href="https://blog.test/posts/post-1"') && str_ends_with($text, '@channel');
        });
    }

    public function test_skips_drafts_future_disabled_and_other_languages(): void
    {
        $this->makePost(['status' => PostStatus::Draft]);
        $this->makePost(['published_at' => now()->addDay()]);
        $this->makePost(['send_to_telegram' => false]);
        $this->makePost(['locale' => 'en']);

        $this->assertCount(0, app(TelegramPublisher::class)->sendDue(pause: 0));
        Http::assertNothingSent();
    }

    public function test_cover_is_sent_as_photo_with_short_caption(): void
    {
        $this->makePost(['cover_path' => 'covers/a.png', 'description' => str_repeat('و', 3000)]);

        app(TelegramPublisher::class)->sendDue(pause: 0);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendPhoto')
            && $request['photo'] === 'https://blog.test/uploads/covers/a.png'
            && mb_strlen($request['caption']) <= TelegramPublisher::CAPTION_LIMIT);
    }

    public function test_baseline_marks_without_sending(): void
    {
        $this->makePost();
        $this->makePost();

        $this->assertSame(2, app(TelegramPublisher::class)->baseline());
        $this->assertCount(0, app(TelegramPublisher::class)->sendDue(pause: 0));
        Http::assertNothingSent();
    }

    public function test_hashtags(): void
    {
        $this->assertSame('#یادگیری_ماشین', TelegramPublisher::hashtag('یادگیری ماشین'));
        $this->assertSame('#نیم_فاصله', TelegramPublisher::hashtag("نیم\u{200C}فاصله"));
        $this->assertSame('#open_source', TelegramPublisher::hashtag('open-source'));
    }
}
