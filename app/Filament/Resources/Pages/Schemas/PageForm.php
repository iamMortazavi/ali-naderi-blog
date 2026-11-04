<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Services\Obsidian\Note;
use App\Support\Locales;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(160),
            Select::make('locale')->label('زبان')->options(Locales::options())
                ->default(Locales::DEFAULT)->required()->live(),
            TextInput::make('slug')->label('آدرس (لاتین)')->required()->maxLength(80)
                ->helperText('حروف کوچک انگلیسی، عدد و خط تیره؛ مثلاً about.')
                ->regex(Note::SLUG)
                // The site's own addresses (and folders under public/) cannot be page addresses.
                ->notIn(Locales::RESERVED_SEGMENTS)
                ->prefix(fn (Get $get) => Locales::path($get('locale') ?: Locales::DEFAULT, '/'))
                ->extraInputAttributes(['dir' => 'ltr'])
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale') ?: Locales::DEFAULT))
                ->validationMessages([
                    'not_in' => 'این آدرس مال خود سایت است؛ آدرس دیگری انتخاب کنید.',
                    'unique' => 'صفحه‌ی دیگری در همین زبان این آدرس را دارد.',
                    'regex' => 'فقط حروف کوچک انگلیسی، عدد و خط تیره.',
                ]),
            TextInput::make('description')->label('توضیح کوتاه (سئو)')->maxLength(300),
            MarkdownEditor::make('body')->label('متن')->required()->columnSpanFull()
                ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('pages')
                ->extraAttributes(fn (Get $get) => ['dir' => Locales::dir($get('locale') ?: Locales::DEFAULT)]),
            Toggle::make('in_sitemap')->label('در نقشه‌ی سایت')->default(true),
        ]);
    }
}
