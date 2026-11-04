<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (Post $record) => $record->path(), shouldOpenInNewTab: true)
                ->visible(fn (Post $record) => $record->isLive()),
            DeleteAction::make()->hidden(fn (Post $record) => $record->isFromObsidian()),
            RestoreAction::make(),
        ];
    }

    /** Posts from Obsidian are read-only here: no save button. */
    protected function getFormActions(): array
    {
        return $this->getRecord()->isFromObsidian() ? [] : parent::getFormActions();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->isFromObsidian()) {
            abort(403, 'Posts from Obsidian are edited in Obsidian.');
        }
        if (($data['body'] ?? null) !== $this->getRecord()->body) {
            $data['content_updated_at'] = now(); // shown as "updated" and used as dateModified
        }

        return $data;
    }
}
