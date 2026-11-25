<?php

namespace App\Application\UseCase\Leave;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeaveRequest;
use App\Domain\User\User;

/** Use case: yönetici talebi açıklamayla reddeder. */
final class RejectLeaveRequest
{
    public function __construct(private LeaveRequestRepository $leaves)
    {
    }

    public function __invoke(User $manager, int $leaveRequestId, string $reason): LeaveRequest
    {
        $leave = $this->leaves->get($leaveRequestId);
        $leave->reject($manager, $reason);
        $this->leaves->save($leave);

        return $leave;
    }
}
