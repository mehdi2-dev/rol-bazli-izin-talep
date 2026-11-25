<?php

namespace App\Application\UseCase\User;

use App\Application\Port\UserRepository;
use App\Domain\User\User;

/** Use case: yöneticinin ekibindeki kullanıcılar. */
final class ListTeamMembers
{
    public function __construct(private UserRepository $users)
    {
    }

    /** @return User[] */
    public function __invoke(User $manager): array
    {
        $department = $manager->department();

        return $department === null ? [] : $this->users->findByDepartment($department);
    }
}
