<?php

namespace App\Application\UseCase\Leave;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeaveRequest;
use App\Domain\User\User;

/** Use case: yönetici talebi onaylar; iş günleri çalışanın bakiyesinden düşer. */
final class ApproveLeaveRequest
{
    public function __construct(private LeaveRequestRepository $leaves)
    {
    }

    public function __invoke(User $manager, int $leaveRequestId): LeaveRequest
    {
        $leave = $this->leaves->get($leaveRequestId);
        $leave->approve($manager);
        $this->leaves->save($leave);

        return $leave;
    }
}
