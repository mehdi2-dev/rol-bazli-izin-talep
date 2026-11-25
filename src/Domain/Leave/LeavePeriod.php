<?php

namespace App\Domain\Leave;

use App\Domain\Exception\ValidationFailed;

/**
 * İzin aralığı (başlangıç ve bitiş dahil). Değişmez (immutable) değer nesnesi.
 */
final class LeavePeriod
{
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;

    public function __construct(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $startDate = $startDate->setTime(0, 0);
        $endDate = $endDate->setTime(0, 0);

        if ($endDate < $startDate) {
            throw new ValidationFailed('Bitiş tarihi başlangıç tarihinden önce olamaz.');
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function startsBefore(\DateTimeImmutable $day): bool
    {
        return $this->startDate < $day->setTime(0, 0);
    }

    /** Hafta sonları hariç iş günü sayısı. */
    public function workingDays(): int
    {
        $days = 0;
        for ($day = $this->startDate; $day <= $this->endDate; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') < 6) {
                ++$days;
            }
        }

        return $days;
    }

    public function overlaps(LeavePeriod $other): bool
    {
        return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
    }
}
