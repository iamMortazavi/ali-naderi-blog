<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'برچسب';

    protected static ?string $pluralModelLabel = 'برچسب‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(60),
            Select::make('locale')->label('زبان')->options(collect(Locales::ALL)->map(fn ($l) => $l['label']))->default('fa')->required(),
            TextInput::make('slug')->label('آدرس')->helperText('خالی بگذارید تا از روی نام ساخته شود.')->maxLength(80),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('posts_count', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->description(fn (Tag $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('posts_count')->label('نوشته‌ها')->sortable(),
            ])
            ->filters([SelectFilter::make('locale')->label('زبان')->options(collect(Locales::ALL)->map(fn ($l) => $l['label']))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTags::route('/')];
    }
}
