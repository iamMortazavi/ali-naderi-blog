<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->description(fn (Page $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('updated_at')->label('آخرین تغییر')->since(),
            ])
            ->filters([SelectFilter::make('locale')->label('زبان')->options(Locales::options())])
            ->recordActions([
                Action::make('site')->label('در سایت')->icon(Heroicon::OutlinedGlobeAlt)->color('gray')
                    ->url(fn (Page $record) => $record->path(), shouldOpenInNewTab: true),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }
}
