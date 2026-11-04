<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostSource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();
        $data['source'] = PostSource::Panel;
        // Not a translation: a key no other post uses, so it is not linked to a post that happens to share its slug.
        if (blank($data['translation_key'] ?? null)) {
            $data['translation_key'] = PostForm::unusedTranslationKey((string) ($data['slug'] ?? ''));
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        return $record instanceof Post ? PostResource::recordUrl($record) : PostResource::getUrl('index');
    }
}
