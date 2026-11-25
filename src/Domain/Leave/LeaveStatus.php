<?php

namespace App\Domain\Leave;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Çakışma kontrolünde dikkate alınan durumlar (reddedilenler yer tutmaz). */
    public function blocksCalendar(): bool
    {
        return $this !== self::Rejected;
    }
}
