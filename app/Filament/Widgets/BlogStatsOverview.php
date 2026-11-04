<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Enums\SyncStatus;
use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\SyncRun;
use App\Models\TelegramMessage;
use App\Support\Dates;
use App\Support\Locales;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlogStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** The numbers change rarely; no polling. */
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $live = Post::query()->live();
        $lastSync = SyncRun::query()->latest('started_at')->first();
        $failedTelegram = TelegramMessage::query()->whereIn('status', [TelegramStatus::Failed, TelegramStatus::Sending])->count();

        return [
            Stat::make('نوشته‌های منتشرشده', (clone $live)->count())
                ->description(collect(Locales::options())
                    ->map(fn (string $label, string $code) => $label.' '.(clone $live)->locale($code)->count())
                    ->implode(' · ')),
            Stat::make('پیش‌نویس و زمان‌بندی‌شده',
                Post::query()->where('status', PostStatus::Draft)->count().' / '.
                Post::query()->where('status', PostStatus::Published)->where('published_at', '>', now())->count())
                ->description('پیش‌نویس / منتظر زمان انتشار'),
            Stat::make('ارسال‌شده به تلگرام', TelegramMessage::query()->where('status', TelegramStatus::Sent)->count())
                ->description($failedTelegram ? $failedTelegram.' ارسال ناموفق یا نامشخص' : null)
                ->color($failedTelegram ? 'danger' : null),
            Stat::make('آخرین همگام‌سازی Obsidian', $lastSync ? Dates::human($lastSync->started_at, 'fa') : '—')
                ->description($lastSync ? ($lastSync->status === SyncStatus::Failed ? $lastSync->errorCount().' خطا' : $lastSync->status->getLabel()) : 'هنوز اجرا نشده')
                ->color($lastSync?->status?->getColor() ?? 'gray'),
        ];
    }
}
