<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\Actions\TelegramActions;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** Read-only view of a post, used for posts the user may not edit (always for posts from Obsidian). */
class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('site')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Post $record) => $record->path(), shouldOpenInNewTab: true)
                ->visible(fn (Post $record) => $record->isLive()),
            EditAction::make(),
            ActionGroup::make([
                TelegramActions::send(),
                TelegramActions::retry(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]),
        ];
    }
}
