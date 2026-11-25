<?php

namespace App\Tests\Support;

use App\Application\Port\UserRepository;
use App\Domain\Exception\NotFound;
use App\Domain\User\Department;
use App\Domain\User\User;

/** Test adaptörü: UserRepository portunu bellekte uygular. */
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $items = [];
    private int $nextId = 1;

    public function get(int $id): User
    {
        return $this->items[$id] ?? throw new NotFound('Kullanıcı bulunamadı.');
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->items as $user) {
            if ($user->email() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findByDepartment(Department $department): array
    {
        $users = array_values(array_filter($this->items, fn (User $u) => $department->equals($u->department())));
        usort($users, fn (User $a, User $b) => strcmp($a->email(), $b->email()));

        return $users;
    }

    public function save(User $user): void
    {
        if ($user->id() === null) {
            Ids::assign($user, $this->nextId++);
        }

        $this->items[$user->id()] = $user;
    }
}
