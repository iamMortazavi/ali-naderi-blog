<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Support\Dates;
use App\Support\Locales;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Latest posts on the dashboard; a row opens the post in the panel. */
class LatestPostsTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('آخرین نوشته‌ها')
            ->query(Post::query()->with('telegramMessage')->latest('published_at')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Post $record) => PostResource::recordUrl($record))
            ->columns([
                TextColumn::make('title')->label('عنوان')->limit(60),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('published_at')->label('تاریخ')->formatStateUsing(fn ($state) => Dates::human($state, 'fa')),
                TextColumn::make('source')->label('منبع')->badge(),
                TextColumn::make('telegram_status')->label('تلگرام')->badge()->placeholder('—')
                    ->state(fn (Post $record) => $record->telegramMessage?->status),
            ]);
    }
}
