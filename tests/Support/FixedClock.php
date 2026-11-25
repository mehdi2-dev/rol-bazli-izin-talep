<?php

namespace App\Tests\Support;

use App\Application\Port\Clock;

/** Test adaptörü: hep aynı anı döndüren saat. */
final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $now = '2030-01-01 09:00')
    {
        $this->now = new \DateTimeImmutable($now);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
