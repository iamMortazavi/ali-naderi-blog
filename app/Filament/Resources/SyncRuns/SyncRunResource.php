<?php

namespace App\Filament\Resources\SyncRuns;

use App\Filament\Resources\SyncRuns\Pages\ListSyncRuns;
use App\Models\SyncRun;
use App\Support\Dates;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** History of Obsidian imports: what changed and which notes have problems. */
class SyncRunResource extends Resource
{
    protected static ?string $model = SyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Obsidian';

    protected static ?string $navigationLabel = 'همگام‌سازی‌ها';

    protected static ?string $modelLabel = 'همگام‌سازی';

    protected static ?string $pluralModelLabel = 'همگام‌سازی‌ها';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(5)->schema([
                TextEntry::make('created_count')->label('جدید'),
                TextEntry::make('updated_count')->label('به‌روزشده'),
                TextEntry::make('unchanged_count')->label('بدون تغییر'),
                TextEntry::make('unpublished_count')->label('از انتشار خارج'),
                TextEntry::make('skipped_count')->label('خصوصی'),
            ])->columnSpanFull(),
            RepeatableEntry::make('messages')->label('پیام‌ها')->columnSpanFull()->schema([
                TextEntry::make('level')->label('')->badge()
                    ->color(fn ($state) => ['error' => 'danger', 'warning' => 'warning'][$state] ?? 'gray'),
                TextEntry::make('file')->label('فایل'),
                TextEntry::make('text')->label('توضیح'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('زمان')
                    ->formatStateUsing(fn ($state) => Dates::human($state, 'fa').' '.$state->format('H:i')),
                TextColumn::make('status')->label('نتیجه')->badge()
                    ->formatStateUsing(fn ($state) => ['success' => 'موفق', 'failed' => 'با خطا', 'running' => 'در حال اجرا'][$state] ?? $state)
                    ->color(fn ($state) => ['success' => 'success', 'failed' => 'danger', 'running' => 'warning'][$state] ?? 'gray'),
                TextColumn::make('source')->label('منبع'),
                TextColumn::make('created_count')->label('جدید'),
                TextColumn::make('updated_count')->label('به‌روز'),
                TextColumn::make('unpublished_count')->label('خارج از انتشار'),
                TextColumn::make('errors')->label('خطا')->state(fn (SyncRun $record) => $record->errorCount())
                    ->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextColumn::make('commit')->label('نسخه')->fontFamily('mono')->toggleable(),
            ])
            ->recordActions([ViewAction::make()->label('جزئیات')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSyncRuns::route('/')];
    }
}
