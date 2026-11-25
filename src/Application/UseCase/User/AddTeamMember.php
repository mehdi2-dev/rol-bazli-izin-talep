<?php

namespace App\Application\UseCase\User;

use App\Application\Port\PasswordHasher;
use App\Application\Port\UserRepository;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\ValidationFailed;
use App\Domain\User\User;

/** Use case: yönetici kendi ekibine yeni çalışan ekler. */
final class AddTeamMember
{
    public const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {
    }

    public function __invoke(User $manager, string $email, string $plainPassword, int $leaveBalance = User::DEFAULT_BALANCE): User
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationFailed('Geçerli bir e-posta adresi girin.');
        }

        if (strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new ValidationFailed('Şifre en az 6 karakter olmalıdır.');
        }

        // Bakiye aralığı domain'de de kontrol edilir; e-posta sorgusundan önce burada yakalanır.
        if ($leaveBalance < User::MIN_BALANCE || $leaveBalance > User::MAX_BALANCE) {
            throw new ValidationFailed('İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new Conflict('Bu e-posta ile kayıtlı bir kullanıcı zaten var.');
        }

        $user = User::create($email, $this->hasher->hash($plainPassword), $manager->department(), $leaveBalance);
        $this->users->save($user);

        return $user;
    }
}
