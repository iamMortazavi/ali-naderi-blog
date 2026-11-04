<?php

namespace App\Filament\Resources\Series\Schemas;

use App\Filament\Resources\Tags\Schemas\TagForm;
use App\Support\Locales;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(120),
            Select::make('locale')->label('زبان')->options(Locales::options())->default(Locales::DEFAULT)->required(),
            TagForm::slugInput(120),
            Textarea::make('description')->label('توضیح')->rows(2)->columnSpanFull(),
        ]);
    }
}
