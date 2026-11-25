<?php

namespace App\Tests\Unit\Domain;

use App\Domain\Exception\ValidationFailed;
use App\Domain\Leave\LeavePeriod;
use PHPUnit\Framework\TestCase;

/** Saf domain testi: veritabanı ve Symfony gerekmez. */
class LeavePeriodTest extends TestCase
{
    private static function period(string $start, string $end): LeavePeriod
    {
        return new LeavePeriod(new \DateTimeImmutable($start), new \DateTimeImmutable($end));
    }

    public function testWorkWeekHasFiveWorkingDays(): void
    {
        self::assertSame(5, self::period('2030-01-07', '2030-01-11')->workingDays()); // pzt–cum
    }

    public function testWeekendIsSkipped(): void
    {
        self::assertSame(2, self::period('2030-01-11', '2030-01-14')->workingDays()); // cum–pzt
        self::assertSame(0, self::period('2030-01-12', '2030-01-13')->workingDays()); // cmt–paz
    }

    public function testSingleDay(): void
    {
        self::assertSame(1, self::period('2030-01-08', '2030-01-08')->workingDays());
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->expectException(ValidationFailed::class);
        $this->expectExceptionMessage('Bitiş tarihi başlangıç tarihinden önce olamaz.');

        self::period('2030-01-11', '2030-01-07');
    }

    public function testTimeOfDayIsIgnored(): void
    {
        $period = self::period('2030-01-07 15:30', '2030-01-07 09:00');

        self::assertSame('2030-01-07 00:00', $period->startDate()->format('Y-m-d H:i'));
        self::assertSame(1, $period->workingDays());
    }

    public function testOverlaps(): void
    {
        $week = self::period('2030-01-07', '2030-01-11');

        self::assertTrue($week->overlaps(self::period('2030-01-11', '2030-01-15')));  // son gün ortak
        self::assertTrue($week->overlaps(self::period('2030-01-08', '2030-01-09')));  // içinde
        self::assertFalse($week->overlaps(self::period('2030-01-12', '2030-01-15'))); // ertesi gün başlıyor
    }

    public function testStartsBefore(): void
    {
        $period = self::period('2030-01-07', '2030-01-08');

        self::assertTrue($period->startsBefore(new \DateTimeImmutable('2030-01-08 10:00')));
        self::assertFalse($period->startsBefore(new \DateTimeImmutable('2030-01-07 23:59')));
    }
}
