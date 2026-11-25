<?php

namespace App\Application\Port;

use App\Domain\Exception\NotFound;
use App\Domain\User\Department;
use App\Domain\User\User;

/** Çıkan port: kullanıcıların saklandığı yer. */
interface UserRepository
{
    /** @throws NotFound */
    public function get(int $id): User;

    public function findByEmail(string $email): ?User;

    /** @return User[] e-postaya göre sıralı */
    public function findByDepartment(Department $department): array;

    public function save(User $user): void;
}
