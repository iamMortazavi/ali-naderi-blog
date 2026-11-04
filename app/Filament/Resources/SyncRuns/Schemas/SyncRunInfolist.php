<?php

namespace App\Filament\Resources\SyncRuns\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SyncRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(5)->schema([
                TextEntry::make('created_count')->label('جدید'),
                TextEntry::make('updated_count')->label('به‌روزشده'),
                TextEntry::make('unchanged_count')->label('بدون تغییر'),
                TextEntry::make('unpublished_count')->label('خارج از انتشار'),
                TextEntry::make('skipped_count')->label('خصوصی'),
            ])->columnSpanFull(),
            RepeatableEntry::make('messages')->label('پیام‌ها')->columnSpanFull()->schema([
                TextEntry::make('level')->label('سطح')->badge()
                    ->formatStateUsing(fn (?string $state) => ['error' => 'خطا', 'warning' => 'هشدار'][$state] ?? 'اطلاع')
                    ->color(fn (?string $state) => ['error' => 'danger', 'warning' => 'warning'][$state] ?? 'gray'),
                TextEntry::make('file')->label('فایل'),
                TextEntry::make('text')->label('توضیح'),
            ])->columns(3),
        ]);
    }
}
