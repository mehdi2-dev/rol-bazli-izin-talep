<?php

namespace App\Application\UseCase\Leave;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\User;

/** Use case: yönetici yalnızca kendi ekibinin taleplerini görür (isteğe bağlı durum filtresiyle). */
final class ListTeamLeaves
{
    public function __construct(private LeaveRequestRepository $leaves)
    {
    }

    /** @return LeaveRequest[] */
    public function __invoke(User $manager, ?LeaveStatus $status = null): array
    {
        $department = $manager->department();

        return $department === null ? [] : $this->leaves->findByDepartment($department, $status);
    }
}
