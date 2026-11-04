<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Support\Url;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RedirectForm
{
    /** A path on this site: one leading slash, not "//host" or "/\host" (browsers treat those as other sites). */
    public const SITE_PATH = '#^/(?![/\\\\])#';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('آدرس قدیمی')->required()->maxLength(255)
                ->placeholder('/posts/old-slug')
                ->helperText('مثل آدرس‌های جدول ذخیره می‌شود: بدون دامنه و بدون / در انتها.')
                ->extraInputAttributes(['dir' => 'ltr'])
                // Stored normalised (Redirect model), so "/posts/old/" and "/posts/old" are the same address.
                ->mutateStateForValidationUsing(fn (?string $state) => self::normalise($state))
                ->dehydrateStateUsing(fn (?string $state) => self::normalise($state))
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'برای این آدرس قبلاً تغییر مسیر ساخته شده است.']),
            TextInput::make('to_path')->label('آدرس جدید')->required()->maxLength(255)
                ->placeholder('/posts/new-slug')
                ->extraInputAttributes(['dir' => 'ltr'])
                ->regex(self::SITE_PATH)
                ->validationMessages(['regex' => 'آدرس جدید باید آدرسی در همین سایت باشد و با یک / شروع شود؛ مثلاً /posts/new-slug.']),
            Select::make('status_code')->label('نوع')->options([301 => 'دائمی (301)', 302 => 'موقت (302)'])->default(301)->required(),
        ]);
    }

    private static function normalise(?string $state): ?string
    {
        return filled($state) ? Url::path(trim($state)) : $state;
    }
}
