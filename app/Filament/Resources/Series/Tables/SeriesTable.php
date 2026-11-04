<?php

namespace App\Filament\Resources\Series\Tables;

use App\Models\Series;
use App\Support\Locales;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->description(fn (Series $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('posts_count')->label('نوشته‌ها')->sortable(),
            ])
            ->filters([SelectFilter::make('locale')->label('زبان')->options(Locales::options())])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()->authorizeIndividualRecords()]);
    }
}
