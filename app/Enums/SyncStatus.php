<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SyncStatus: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'در حال اجرا',
            self::Success => 'موفق',
            self::Failed => 'با خطا',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'warning',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
