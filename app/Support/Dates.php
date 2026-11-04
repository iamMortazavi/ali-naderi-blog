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

    /** Current year in the calendar of the given language, with matching digits. */
    public static function year(string $locale): string
    {
        return $locale === 'fa' ? Persian::digits(Jalali::fromDate(now())[0]) : now()->format('Y');
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
