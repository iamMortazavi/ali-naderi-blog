<?php

namespace App\Filament\Resources\Tags\Tables;

use App\Models\Tag;
use App\Support\Locales;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('posts_count', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->description(fn (Tag $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('posts_count')->label('نوشته‌ها')->sortable(),
            ])
            ->filters([SelectFilter::make('locale')->label('زبان')->options(Locales::options())])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()->authorizeIndividualRecords()]);
    }
}
