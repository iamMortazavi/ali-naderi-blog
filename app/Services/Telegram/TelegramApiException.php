<?php

namespace App\Services\Telegram;

use RuntimeException;

/** Telegram answered and refused the request (ok = false): the message was definitely not sent. */
class TelegramApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $errorCode = 0)
    {
        parent::__construct($message, $errorCode);
    }
}
