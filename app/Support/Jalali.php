<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Gregorian → Jalali (Persian calendar) conversion, using the integer algorithm from jalaali-js.
 */
final class Jalali
{
    public const MONTHS = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    /** @return array{0:int,1:int,2:int} [year, month, day] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $cumulative = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $cumulative[$gm - 1];
        $jy = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + $days % 31];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + ($days - 186) % 30];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function fromDate(DateTimeInterface $date): array
    {
        $date = Dates::local($date);

        return self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    /** "۱۰ آبان ۱۴۰۵" */
    public static function format(DateTimeInterface $date): string
    {
        [$y, $m, $d] = self::fromDate($date);

        return Persian::digits("{$d} ".self::MONTHS[$m - 1]." {$y}");
    }

    public static function monthName(int $month): string
    {
        return self::MONTHS[$month - 1];
    }
}
