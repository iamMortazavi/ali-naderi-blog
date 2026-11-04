<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\SyncRun;
use App\Models\TelegramMessage;
use App\Support\Dates;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlogStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $live = Post::query()->live();
        $lastSync = SyncRun::query()->latest('started_at')->first();

        return [
            Stat::make('نوشته‌های منتشرشده', (clone $live)->count())
                ->description('فارسی '.(clone $live)->where('locale', 'fa')->count().' · انگلیسی '.(clone $live)->where('locale', 'en')->count()),
            Stat::make('پیش‌نویس و زمان‌بندی‌شده',
                Post::query()->where('status', PostStatus::Draft)->count().' / '.
                Post::query()->where('status', PostStatus::Published)->where('published_at', '>', now())->count())
                ->description('پیش‌نویس / منتظر زمان انتشار'),
            Stat::make('ارسال‌شده به تلگرام', TelegramMessage::query()->whereNotNull('message_id')->count()),
            Stat::make('آخرین همگام‌سازی Obsidian', $lastSync ? Dates::human($lastSync->started_at, 'fa') : '—')
                ->description($lastSync ? ($lastSync->status === 'success' ? 'موفق' : $lastSync->errorCount().' خطا') : 'هنوز اجرا نشده')
                ->color($lastSync?->status === 'failed' ? 'danger' : 'success'),
        ];
    }
}
