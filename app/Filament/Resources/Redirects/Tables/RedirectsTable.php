<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('از')->searchable(),
                TextColumn::make('to_path')->label('به')->searchable(),
                TextColumn::make('status_code')->label('نوع'),
                TextColumn::make('hits')->label('بازدید')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()->authorizeIndividualRecords()]);
    }
}
