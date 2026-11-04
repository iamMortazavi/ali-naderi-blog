<?php

namespace App\Filament\Resources\Series;

use App\Filament\Resources\Series\Pages\ManageSeries;
use App\Models\Series;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SeriesResource extends Resource
{
    protected static ?string $model = Series::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'مجموعه';

    protected static ?string $pluralModelLabel = 'مجموعه‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(120),
            Select::make('locale')->label('زبان')->options(collect(Locales::ALL)->map(fn ($l) => $l['label']))->default('fa')->required(),
            TextInput::make('slug')->label('آدرس')->helperText('خالی بگذارید تا از روی نام ساخته شود.')->maxLength(120),
            Textarea::make('description')->label('توضیح')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->description(fn (Series $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('posts_count')->label('نوشته‌ها'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeries::route('/')];
    }
}
