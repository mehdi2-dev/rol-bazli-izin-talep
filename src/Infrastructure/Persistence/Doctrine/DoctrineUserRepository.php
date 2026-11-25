<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\UserRepository;
use App\Domain\Exception\NotFound;
use App\Domain\User\Department;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

/** Adaptör: UserRepository portunu Doctrine ORM ile uygular. */
final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(int $id): User
    {
        return $this->em->find(User::class, $id)
            ?? throw new NotFound('Kullanıcı bulunamadı.');
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findByDepartment(Department $department): array
    {
        return $this->em->getRepository(User::class)->findBy(['department' => $department], ['email' => 'ASC']);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
