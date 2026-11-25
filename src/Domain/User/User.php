<?php

namespace App\Domain\User;

use App\Domain\Exception\ValidationFailed;

/**
 * Çalışan veya yönetici.
 *
 * Framework'ten bağımsızdır: Symfony güvenliği bu sınıfı
 * Infrastructure\Security\SecurityUser ile sarar.
 */
class User
{
    public const ROLE_EMPLOYEE = 'ROLE_USER';
    public const ROLE_MANAGER = 'ROLE_ADMIN';

    public const DEFAULT_BALANCE = 14;
    public const MIN_BALANCE = 0;
    public const MAX_BALANCE = 60;

    private ?int $id = null;

    /** @param string[] $roles */
    private function __construct(
        private string $email,
        private string $passwordHash,
        private ?Department $department,
        private int $leaveBalance,
        private array $roles,
    ) {
    }

    /** @param string[] $roles */
    public static function create(
        string $email,
        string $passwordHash,
        ?Department $department,
        int $leaveBalance = self::DEFAULT_BALANCE,
        array $roles = [self::ROLE_EMPLOYEE],
    ): self {
        self::assertValidBalance($leaveBalance);

        return new self($email, $passwordHash, $department, $leaveBalance, $roles);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function department(): ?Department
    {
        return $this->department;
    }

    public function leaveBalance(): int
    {
        return $this->leaveBalance;
    }

    /** @return string[] Her kullanıcı en az çalışandır (ROLE_USER). */
    public function roles(): array
    {
        return array_values(array_unique([...$this->roles, self::ROLE_EMPLOYEE]));
    }

    public function isManager(): bool
    {
        return in_array(self::ROLE_MANAGER, $this->roles(), true);
    }

    public function sameAs(User $other): bool
    {
        return $this === $other || ($this->id !== null && $this->id === $other->id);
    }

    /** İki kullanıcı da bir ekipteyse ve aynı ekipteyse true. */
    public function isInSameTeamAs(User $other): bool
    {
        return $this->department !== null && $this->department->equals($other->department);
    }

    public function hasBalanceFor(int $days): bool
    {
        return $days <= $this->leaveBalance;
    }

    /** Onaylanan izin bakiyeden düşülür. */
    public function deductLeave(int $days): void
    {
        if (!$this->hasBalanceFor($days)) {
            throw new ValidationFailed(sprintf(
                'Çalışanın bakiyesi yetersiz. Talep: %d gün, kalan: %d gün.',
                $days,
                $this->leaveBalance,
            ));
        }

        $this->leaveBalance -= $days;
    }

    public function changeLeaveBalance(int $balance): void
    {
        self::assertValidBalance($balance);
        $this->leaveBalance = $balance;
    }

    private static function assertValidBalance(int $balance): void
    {
        if ($balance < self::MIN_BALANCE || $balance > self::MAX_BALANCE) {
            throw new ValidationFailed('İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.');
        }
    }
}
