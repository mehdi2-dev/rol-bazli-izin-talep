<?php

namespace App\Application\UseCase\Leave;

use App\Application\Port\Clock;
use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\User\User;

/** Use case: çalışan izin talebi oluşturur. */
final class SubmitLeaveRequest
{
    public function __construct(
        private LeaveRequestRepository $leaves,
        private Clock $clock,
    ) {
    }

    public function __invoke(User $employee, \DateTimeImmutable $start, \DateTimeImmutable $end, string $reason): LeaveRequest
    {
        $period = new LeavePeriod($start, $end);

        $leave = LeaveRequest::submit(
            $employee,
            $period,
            $reason,
            $this->clock->now(),
            $this->leaves->hasOverlapping($employee, $period),
        );
        $this->leaves->save($leave);

        return $leave;
    }
}
