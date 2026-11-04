<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\Actions\TelegramActions;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** Panel posts only: PostPolicy::update() refuses Obsidian posts, which open on ViewPost instead. */
class EditPost extends EditRecord
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
            ActionGroup::make([
                TelegramActions::send(),
                TelegramActions::retry(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Post $post */
        $post = $this->getRecord();

        // The "translation of" field is only sent when it is filled. Empty means "not a translation":
        // a key shared with another-language post is replaced by one nobody uses; any other key stays.
        if (blank($data['translation_key'] ?? null)) {
            unset($data['translation_key']);
            if (PostForm::linkedTranslationKey($post) !== null) {
                $data['translation_key'] = PostForm::unusedTranslationKey((string) ($data['slug'] ?? $post->slug), $post);
            }
        }

        if (($data['body'] ?? null) !== $post->body) {
            $data['content_updated_at'] = now(); // shown as "updated" and used as dateModified
        }

        return $data;
    }
}
