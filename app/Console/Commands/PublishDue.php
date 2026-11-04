<?php

namespace App\Console\Commands;

use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\TelegramMessage;
use App\Services\Telegram\TelegramPublisher;
use App\Support\PageCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Runs every minute from the scheduler. The last check time is cached as a plain integer timestamp:
 * the cache may not unserialize objects (cache.serializable_classes = false), so a Carbon would come
 * back as __PHP_Incomplete_Class.
 */
#[Signature('blog:publish-due {--baseline : Mark every due post as already sent, without sending}')]
#[Description('Bring scheduled posts live and send new posts to Telegram')]
class PublishDue extends Command
{
    public const LAST_CHECK_KEY = 'blog:last-publish-check';

    public function handle(TelegramPublisher $telegram): int
    {
        if ($this->option('baseline')) {
            $this->info($telegram->baseline().' post(s) marked as sent.');

            return self::SUCCESS;
        }

        // Scheduled posts whose time has come: the cached pages must be refreshed to show them.
        $now = now();
        $last = Cache::get(self::LAST_CHECK_KEY);
        $since = is_int($last) ? Carbon::createFromTimestamp($last, config('app.timezone')) : $now->copy()->subMinute();
        if (Post::query()->live()->where('published_at', '>', $since)->exists()) {
            PageCache::flush();
        }
        Cache::forever(self::LAST_CHECK_KEY, $now->getTimestamp());

        $failed = false;
        foreach ($telegram->sendDue() as $row) {
            $post = $row->post;
            $link = '<a href="'.e($post->url()).'">'.e($post->title).'</a>';
            if ($row->status === TelegramStatus::Sent) {
                $this->line("sent {$post->locale}/{$post->slug}");
                $telegram->notify('✅ منتشر شد: '.$link);

                continue;
            }
            $failed = true;
            $this->error("not sent {$post->locale}/{$post->slug}: {$row->last_error}");
            if (self::firstFailure($row)) {
                $telegram->notify(($row->status === TelegramStatus::Sending
                    ? '⚠️ تلگرام پاسخی نداد؛ شاید پیام در کانال منتشر شده باشد. کانال را بررسی کنید و در صورت نیاز از پنل دوباره بفرستید: '
                    : '❌ ارسال به کانال تلگرام انجام نشد: ').$link."\n".e((string) $row->last_error));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Notify once per failure: when a row first becomes failed (its first attempt), or when the
     * result became unknown (such rows are never retried automatically, so this happens once).
     */
    public static function firstFailure(TelegramMessage $row): bool
    {
        return $row->status === TelegramStatus::Sending
            || ($row->status === TelegramStatus::Failed && (int) $row->attempts === 1);
    }
}
