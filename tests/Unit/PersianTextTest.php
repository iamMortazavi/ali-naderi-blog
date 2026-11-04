<?php

namespace Tests\Unit;

use App\Support\Jalali;
use App\Support\Persian;
use PHPUnit\Framework\TestCase;

class PersianTextTest extends TestCase
{
    public function test_gregorian_to_jalali(): void
    {
        $this->assertSame([1405, 8, 1], Jalali::fromGregorian(2026, 10, 23));
        $this->assertSame([1405, 1, 1], Jalali::fromGregorian(2026, 3, 21));
        $this->assertSame([1404, 12, 29], Jalali::fromGregorian(2026, 3, 20));
        $this->assertSame([1403, 1, 1], Jalali::fromGregorian(2024, 3, 20));
        $this->assertSame([1405, 12, 29], Jalali::fromGregorian(2027, 3, 20));
    }

    public function test_digits_and_normalize(): void
    {
        $this->assertSame('۱۴۰۵', Persian::digits('1405'));
        $this->assertSame('یادداشت', Persian::normalize('يادداشت'));
        $this->assertSame('کتاب', Persian::normalize('كتاب'));
        $this->assertSame('می نویسم 12', Persian::normalize("می\u{200C}نویسم ۱۲"));
    }

    public function test_word_count_keeps_half_space_words_together(): void
    {
        $this->assertSame(3, Persian::wordCount("نوشته\u{200C}های این وبلاگ"));
    }
}
