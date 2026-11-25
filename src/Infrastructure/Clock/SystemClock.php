<?php

namespace App\Infrastructure\Clock;

use App\Application\Port\Clock;

/** Adaptör: gerçek sistem saati. */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
