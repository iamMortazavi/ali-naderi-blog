<?php

namespace App\Filament\Resources\SyncRuns\Pages;

use App\Filament\Resources\SyncRuns\SyncRunResource;
use App\Services\Obsidian\VaultSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
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
                        $vault = VaultSync::fromSettings();
                    } catch (Throwable $e) {
                        Notification::make()->title('منبع Obsidian درست تنظیم نشده')->body($e->getMessage())->danger()->send();

                        return;
                    }
                    if (! $vault) {
                        Notification::make()->title('همگام‌سازی خاموش است')->body('در تنظیمات، بخش Obsidian، منبع یادداشت‌ها را انتخاب کنید.')->warning()->send();

                        return;
                    }
                    $run = app(VaultSync::class)->run($vault);
                    $notice = Notification::make()
                        ->title($run->status === 'success' ? 'همگام‌سازی انجام شد' : 'همگام‌سازی با خطا تمام شد')
                        ->body("جدید: {$run->created_count}، به‌روز: {$run->updated_count}، خطا: {$run->errorCount()}");
                    ($run->status === 'success' ? $notice->success() : $notice->danger())->send();
                }),
        ];
    }
}
