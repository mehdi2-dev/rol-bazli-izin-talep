<?php

namespace App\Application\UseCase\Leave;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeaveRequest;
use App\Domain\User\User;

/** Use case: çalışan yalnızca kendi taleplerini görür. */
final class ListMyLeaves
{
    public function __construct(private LeaveRequestRepository $leaves)
    {
    }

    /** @return LeaveRequest[] */
    public function __invoke(User $employee): array
    {
        return $this->leaves->findByEmployee($employee);
    }
}
