<?php

namespace App\Support;

final class Persian
{
    private const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function digits(string|int $text): string
    {
        return str_replace(range(0, 9), self::DIGITS, (string) $text);
    }

    /**
     * Text as stored for search and compared with queries: Arabic ي/ك become Persian ی/ک,
     * diacritics and half-spaces are removed, Persian digits become Latin, everything lower case.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            "\u{200C}" => ' ', "\u{200E}" => '', "\u{200F}" => '',
        ]);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace(self::DIGITS, range(0, 9), $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Words in a text, counting Persian words joined by half-spaces as one word. */
    public static function wordCount(string $plain): int
    {
        return count(preg_split('/[\s]+/u', trim($plain), -1, PREG_SPLIT_NO_EMPTY));
    }
}
