<?php

namespace App\Filament\Resources\SyncRuns\Tables;

use App\Enums\SyncStatus;
use App\Models\SyncRun;
use App\Support\Dates;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('زمان')
                    ->formatStateUsing(fn ($state) => Dates::human($state, 'fa').' '.$state->format('H:i')),
                TextColumn::make('status')->label('نتیجه')->badge(),
                TextColumn::make('source')->label('منبع'),
                TextColumn::make('created_count')->label('جدید'),
                TextColumn::make('updated_count')->label('به‌روزشده'),
                TextColumn::make('unpublished_count')->label('خارج از انتشار'),
                TextColumn::make('errors')->label('خطا')->state(fn (SyncRun $record) => $record->errorCount())
                    ->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextColumn::make('commit')->label('نسخه')->fontFamily('mono')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('نتیجه')->options(SyncStatus::class),
            ])
            ->recordActions([ViewAction::make()->label('جزئیات')]);
    }
}
