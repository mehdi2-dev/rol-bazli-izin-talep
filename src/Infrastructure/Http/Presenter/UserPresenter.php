<?php

namespace App\Infrastructure\Http\Presenter;

use App\Domain\User\User;

/** Domain kullanıcısını API'nin JSON biçimine çevirir. Şifre özeti asla dışarı verilmez. */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(User $user): array
    {
        return [
            'id' => $user->id(),
            'email' => $user->email(),
            'roles' => $user->roles(),
            'isManager' => $user->isManager(),
            'department' => $user->department()?->name(),
            'leaveBalance' => $user->leaveBalance(),
        ];
    }

    /**
     * @param User[] $users
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $users): array
    {
        return array_values(array_map(self::toArray(...), $users));
    }
}
