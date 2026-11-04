<?php

namespace App\Services\Telegram;

use RuntimeException;

/** No usable answer from Telegram (timeout, connection error, not JSON): the message may or may not be sent. */
class TelegramUnknownResultException extends RuntimeException {}
