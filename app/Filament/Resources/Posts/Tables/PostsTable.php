<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Actions\TelegramActions;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Support\Dates;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->recordUrl(fn (Post $record) => PostResource::recordUrl($record))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(['title', 'slug'])
                    ->description(fn (Post $record) => $record->path())
                    ->wrap()
                    ->limit(70),
                TextColumn::make('locale')
                    ->label('زبان')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Locales::label($state)),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (PostStatus $state, Post $record) => $state === PostStatus::Published && $record->published_at?->isFuture() ? 'زمان‌بندی‌شده' : $state->getLabel())
                    ->color(fn (PostStatus $state, Post $record) => $state === PostStatus::Published && $record->published_at?->isFuture() ? 'warning' : $state->getColor()),
                TextColumn::make('published_at')
                    ->label('تاریخ انتشار')
                    ->formatStateUsing(fn ($state) => Dates::human($state, 'fa'))
                    ->sortable(),
                TextColumn::make('source')
                    ->label('منبع')
                    ->badge(),
                TextColumn::make('telegram_status')
                    ->label('تلگرام')
                    ->state(fn (Post $record) => $record->telegramMessage?->status)
                    ->badge()
                    ->placeholder('—')
                    ->tooltip(fn (Post $record) => TelegramActions::lastError($record)
                        ?? ($record->telegramMessage?->sent_at ? Dates::human($record->telegramMessage->sent_at, 'fa') : null)),
                TextColumn::make('word_count')
                    ->label('کلمات')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('telegramMessage'))
            ->filters([
                SelectFilter::make('locale')->label('زبان')->options(Locales::options()),
                SelectFilter::make('status')->label('وضعیت')->options(PostStatus::class),
                SelectFilter::make('source')->label('منبع')->options(PostSource::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('site')
                    ->label('در سایت')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->color('gray')
                    ->url(fn (Post $record) => $record->path(), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record) => $record->isLive()),
                ViewAction::make()->hidden(fn (Post $record) => PostResource::canEdit($record)),
                EditAction::make(),
                ActionGroup::make([
                    TelegramActions::send(),
                    TelegramActions::retry(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                // Each selected post is checked against PostPolicy; the ones the user may not touch are skipped.
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                    RestoreBulkAction::make()->authorizeIndividualRecords(),
                    ForceDeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
