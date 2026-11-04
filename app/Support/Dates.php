<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class Dates
{
    public static function local(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(config('app.timezone'));
    }

    /** Human date in the given language: "۱۰ آبان ۱۴۰۵" or "Nov 1, 2026". */
    public static function human(?DateTimeInterface $date, string $locale): string
    {
        if (! $date) {
            return '';
        }

        return $locale === 'fa' ? Jalali::format($date) : self::local($date)->format('M j, Y');
    }
}
