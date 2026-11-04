<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Old address → new address. Added automatically when a published post changes slug. */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'تغییر مسیر';

    protected static ?string $pluralModelLabel = 'تغییر مسیرها';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('آدرس قدیمی')->required()->placeholder('/posts/old-slug')
                ->extraInputAttributes(['dir' => 'ltr'])->unique(ignoreRecord: true),
            TextInput::make('to_path')->label('آدرس جدید')->required()->placeholder('/posts/new-slug')
                ->extraInputAttributes(['dir' => 'ltr']),
            Select::make('status_code')->label('نوع')->options([301 => 'دائمی (301)', 302 => 'موقت (302)'])->default(301)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('از')->searchable(),
                TextColumn::make('to_path')->label('به')->searchable(),
                TextColumn::make('status_code')->label('نوع'),
                TextColumn::make('hits')->label('بازدید')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
