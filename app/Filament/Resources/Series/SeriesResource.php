<?php

namespace App\Filament\Resources\Series;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Series\Pages\ManageSeries;
use App\Filament\Resources\Series\Schemas\SeriesForm;
use App\Filament\Resources\Series\Tables\SeriesTable;
use App\Models\Series;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Multi-part series, per language. Editors may add and change them; only admins delete (SeriesPolicy). */
class SeriesResource extends Resource
{
    protected static ?string $model = Series::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Content;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'مجموعه';

    protected static ?string $pluralModelLabel = 'مجموعه‌ها';

    public static function form(Schema $schema): Schema
    {
        return SeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeries::route('/')];
    }
}
