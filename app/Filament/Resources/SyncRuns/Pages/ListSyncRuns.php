<?php

namespace App\Filament\Resources\SyncRuns\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\SyncRuns\SyncRunResource;
use App\Services\Obsidian\VaultFactory;
use App\Services\Obsidian\VaultSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use Throwable;

class ListSyncRuns extends ListRecords
{
    protected static string $resource = SyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('همگام‌سازی الان')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function () {
                    try {
                        $vault = VaultFactory::fromSettings();
                    } catch (RuntimeException $e) {
                        Notification::make()->title('منبع Obsidian درست تنظیم نشده')->body($e->getMessage())->danger()->send();

                        return;
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('منبع Obsidian درست تنظیم نشده')->body('جزئیات در گزارش سرور ثبت شد.')->danger()->send();

                        return;
                    }
                    if (! $vault) {
                        Notification::make()->title('همگام‌سازی خاموش است')->body('در تنظیمات، بخش Obsidian، منبع یادداشت‌ها را انتخاب کنید.')->warning()->send();

                        return;
                    }
                    $run = app(VaultSync::class)->run($vault, force: true); // a manual sync always runs
                    $ok = $run->status === SyncStatus::Success;
                    $notice = Notification::make()
                        ->title($ok ? 'همگام‌سازی انجام شد' : 'همگام‌سازی با خطا تمام شد')
                        ->body("جدید: {$run->created_count}، به‌روزشده: {$run->updated_count}، خطا: {$run->errorCount()}");
                    ($ok ? $notice->success() : $notice->danger())->send();
                }),
        ];
    }
}
