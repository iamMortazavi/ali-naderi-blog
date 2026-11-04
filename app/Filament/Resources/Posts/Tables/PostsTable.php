<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Telegram\TelegramPublisher;
use App\Support\Dates;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Throwable;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
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
                IconColumn::make('telegramMessage')
                    ->label('تلگرام')
                    ->state(fn (Post $record) => $record->telegramMessage !== null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedPaperAirplane)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->tooltip(fn (Post $record) => $record->telegramMessage ? 'ارسال شده: '.Dates::human($record->telegramMessage->sent_at, 'fa') : 'ارسال نشده'),
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
                SelectFilter::make('locale')->label('زبان')->options(collect(Locales::ALL)->map(fn ($l) => $l['label'])),
                SelectFilter::make('status')->label('وضعیت')->options(PostStatus::class),
                SelectFilter::make('source')->label('منبع')->options(PostSource::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('مشاهده')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Post $record) => $record->path(), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record) => $record->isLive()),
                EditAction::make()->label(fn (Post $record) => $record->isFromObsidian() ? 'جزئیات' : 'ویرایش'),
                ActionGroup::make([
                    Action::make('telegram')
                        ->label('ارسال به تلگرام')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->requiresConfirmation()
                        ->modalDescription('این نوشته همین حالا در کانال منتشر می‌شود. هر نوشته فقط یک بار ارسال می‌شود.')
                        ->visible(fn (Post $record) => $record->isLive() && ! $record->telegramMessage && app(TelegramPublisher::class)->configured())
                        ->action(function (Post $record) {
                            try {
                                app(TelegramPublisher::class)->send($record);
                                Notification::make()->title('در کانال تلگرام منتشر شد')->success()->send();
                            } catch (Throwable $e) {
                                Notification::make()->title('ارسال انجام نشد')->body($e->getMessage())->danger()->send();
                            }
                        }),
                    DeleteAction::make()->hidden(fn (Post $record) => $record->isFromObsidian()),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
