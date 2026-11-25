<?php

namespace App\Application\UseCase\User;

use App\Application\Port\UserRepository;
use App\Domain\Exception\AccessDenied;
use App\Domain\User\User;

/** Use case: yönetici ekibindeki çalışanın izin bakiyesini değiştirir. */
final class ChangeLeaveBalance
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(User $manager, int $userId, int $leaveBalance): User
    {
        $user = $this->users->get($userId);

        if (!$manager->isInSameTeamAs($user)) {
            throw new AccessDenied('Bu kullanıcı sizin ekibinizde değil.');
        }

        $user->changeLeaveBalance($leaveBalance);
        $this->users->save($user);

        return $user;
    }
}
