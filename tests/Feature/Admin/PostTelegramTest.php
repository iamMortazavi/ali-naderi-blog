<?php

namespace Tests\Feature\Admin;

use App\Enums\TelegramStatus;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

class PostTelegramTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '123:SECRET-TOKEN', 'services.telegram.chat_id' => '@channel']);
        SiteSettings::put('site', ['url' => 'https://blog.test', 'title_fa' => 'وبلاگ', 'title_en' => 'Blog']);
    }

    public function test_send_is_offered_only_for_live_unsent_posts(): void
    {
        $this->actingAs($this->admin());
        $live = $this->makePost();
        $draft = $this->makePost(['status' => 'draft']);
        $sent = $this->makePost();
        $sent->telegramMessage()->create(['chat_id' => '@channel', 'status' => TelegramStatus::Sent, 'message_id' => 5, 'sent_at' => now()]);

        Livewire::test(ListPosts::class)
            ->assertTableActionVisible('telegram', $live)
            ->assertTableActionHidden('telegram', $draft)
            ->assertTableActionHidden('telegram', $sent);

        config(['services.telegram.bot_token' => null]);
        Livewire::test(ListPosts::class)->assertTableActionHidden('telegram', $live);
    }

    public function test_a_failed_send_shows_a_generic_message_without_the_token(): void
    {
        Http::fake(fn () => throw new \RuntimeException('cURL error for https://api.telegram.org/bot123:SECRET-TOKEN/sendMessage'));
        $this->actingAs($this->admin());
        $post = $this->makePost();

        $component = Livewire::test(ListPosts::class)
            ->callTableAction('telegram', $post)
            ->assertNotified('ارسال به تلگرام انجام نشد');

        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode(session()->all()));
        $this->assertStringNotContainsString('SECRET-TOKEN', $component->html());
    }

    public function test_status_and_error_are_shown_and_retry_is_admin_only(): void
    {
        $editor = $this->editor();
        $post = $this->makePost(['author_id' => $editor->id]);
        $post->telegramMessage()->create([
            'chat_id' => '@channel', 'status' => TelegramStatus::Failed, 'attempts' => 3,
            'last_error' => 'Bad Request: chat not found (bot123:SECRET-TOKEN)',
        ]);

        $this->actingAs($editor);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSee(TelegramStatus::Failed->getLabel())
            ->assertSee('chat not found')
            ->assertDontSee('SECRET-TOKEN');
        Livewire::test(ListPosts::class)->assertTableActionHidden('telegramRetry', $post);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        $this->actingAs($this->admin());
        Livewire::test(ListPosts::class)
            ->assertTableActionVisible('telegramRetry', $post)
            ->callTableAction('telegramRetry', $post)
            ->assertNotified('در کانال تلگرام منتشر شد');

        $this->assertSame(TelegramStatus::Sent, $post->telegramMessage()->first()->status);
    }
}
