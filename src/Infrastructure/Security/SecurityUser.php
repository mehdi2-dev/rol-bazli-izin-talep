<?php

namespace App\Infrastructure\Security;

use App\Domain\User\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptör: domain kullanıcısını Symfony güvenliğinin beklediği arayüze sarar.
 * Böylece Domain\User\User Symfony'ye bağımlı olmaz.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private User $user)
    {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function getUserIdentifier(): string
    {
        return $this->user->email();
    }

    public function getRoles(): array
    {
        return $this->user->roles();
    }

    public function getPassword(): string
    {
        return $this->user->passwordHash();
    }

    public function eraseCredentials(): void
    {
    }
}
