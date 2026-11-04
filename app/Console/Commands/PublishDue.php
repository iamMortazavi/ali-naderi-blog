<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Telegram\TelegramPublisher;
use App\Support\PageCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('blog:publish-due {--baseline : Mark every live post as already sent, without sending}')]
#[Description('Bring scheduled posts live and send new posts to Telegram')]
class PublishDue extends Command
{
    public function handle(TelegramPublisher $telegram): int
    {
        if ($this->option('baseline')) {
            $this->info($telegram->baseline().' post(s) marked as sent.');

            return self::SUCCESS;
        }

        // Scheduled posts whose time has come: the cached pages must be refreshed to show them.
        $since = Cache::get('blog:last-publish-check', now()->subMinute());
        if (Post::query()->live()->where('published_at', '>', $since)->exists()) {
            PageCache::flush();
        }
        Cache::forever('blog:last-publish-check', now());

        try {
            $sent = $telegram->sendDue();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $telegram->notify('❌ ارسال به کانال تلگرام انجام نشد: '.e($e->getMessage()));

            return self::FAILURE;
        }
        foreach ($sent as $post) {
            $this->line("sent {$post->locale}/{$post->slug}");
            $telegram->notify('✅ منتشر شد: <a href="'.e($post->url()).'">'.e($post->title).'</a>');
        }

        return self::SUCCESS;
    }
}
