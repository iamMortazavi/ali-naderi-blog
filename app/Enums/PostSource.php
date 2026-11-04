<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PostSource: string implements HasColor, HasLabel
{
    case Panel = 'panel';
    case Obsidian = 'obsidian';

    public function getLabel(): string
    {
        return match ($this) {
            self::Panel => 'پنل',
            self::Obsidian => 'Obsidian',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Panel => 'info',
            self::Obsidian => 'warning',
        };
    }
}
