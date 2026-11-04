<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Models\Tag;
use App\Support\Locales;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TagForm
{
    /** Tag and series addresses: letters (Persian too) and digits, words joined by single dashes. */
    public const SLUG = '/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(60),
            Select::make('locale')->label('زبان')->options(Locales::options())->default(Locales::DEFAULT)->required(),
            self::slugInput(80),
        ]);
    }

    /**
     * Address field shared with series. Left empty, it is made from the name (Tag::slugFor) before
     * validation, so the per-language unique rule checks the address that will really be saved.
     */
    public static function slugInput(int $maxLength): TextInput
    {
        return TextInput::make('slug')
            ->label('آدرس')
            ->helperText('خالی بگذارید تا از روی نام ساخته شود. حروف، عدد و خط تیره.')
            ->maxLength($maxLength)
            ->extraInputAttributes(['dir' => 'auto'])
            ->mutateStateForValidationUsing(fn (?string $state, Get $get) => self::slug($state, $get('name')))
            ->dehydrateStateUsing(fn (?string $state, Get $get) => self::slug($state, $get('name')))
            ->regex(self::SLUG)
            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale') ?: Locales::DEFAULT))
            ->validationMessages([
                'unique' => 'این آدرس در همین زبان قبلاً استفاده شده است.',
                'regex' => 'آدرس فقط حروف، عدد و خط تیره (نه در ابتدا یا انتها، نه پشت سر هم) می‌تواند داشته باشد.',
            ]);
    }

    private static function slug(?string $state, ?string $name): string
    {
        $state = trim((string) $state);

        return $state !== '' ? mb_strtolower($state) : Tag::slugFor((string) $name);
    }
}
