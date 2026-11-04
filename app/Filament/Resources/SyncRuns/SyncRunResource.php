<?php

namespace App\Filament\Resources\SyncRuns;

use App\Filament\NavigationGroup;
use App\Filament\Resources\SyncRuns\Pages\ListSyncRuns;
use App\Filament\Resources\SyncRuns\Schemas\SyncRunInfolist;
use App\Filament\Resources\SyncRuns\Tables\SyncRunsTable;
use App\Models\SyncRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** History of Obsidian imports: what changed and which notes have problems. Admin only (SyncRunPolicy). */
class SyncRunResource extends Resource
{
    protected static ?string $model = SyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Obsidian;

    protected static ?string $navigationLabel = 'همگام‌سازی‌ها';

    protected static ?string $modelLabel = 'همگام‌سازی';

    protected static ?string $pluralModelLabel = 'همگام‌سازی‌ها';

    public static function infolist(Schema $schema): Schema
    {
        return SyncRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SyncRunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListSyncRuns::route('/')];
    }
}
