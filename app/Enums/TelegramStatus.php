<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * State of a post's single channel message (telegram_messages.status).
 * A row is claimed as Sending before the API call, so a post can never be sent twice.
 */
enum TelegramStatus: string implements HasColor, HasLabel
{
    case Sending = 'sending';   // claimed; if it stays here, the result is unknown (timeout)
    case Sent = 'sent';
    case Failed = 'failed';     // Telegram refused it; retried a few times, then left for the panel
    case Skipped = 'skipped';   // marked as sent without sending (old posts)

    public function getLabel(): string
    {
        return match ($this) {
            self::Sending => 'نامشخص',
            self::Sent => 'ارسال شده',
            self::Failed => 'ناموفق',
            self::Skipped => 'ارسال نشده (قدیمی)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }
}
