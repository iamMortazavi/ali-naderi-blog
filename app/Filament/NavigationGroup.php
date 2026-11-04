<?php

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Content;
    case Obsidian;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Content => 'محتوا',
            self::Obsidian => 'Obsidian',
            self::Settings => 'تنظیمات',
        };
    }
}
