<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Where Obsidian notes are read from (Settings → Obsidian). */
enum SyncDriver: string implements HasLabel
{
    case None = 'none';
    case GitHub = 'github';
    case Local = 'local';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'خاموش: نوشته‌ها فقط در پنل نوشته می‌شوند',
            self::GitHub => 'مخزن GitHub: Obsidian با افزونه‌ی obsidian-git در آن ذخیره می‌کند',
            self::Local => 'پوشه‌ای روی همین سرور',
        };
    }
}
