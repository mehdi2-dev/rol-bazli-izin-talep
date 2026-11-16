<?php

namespace App\DataFixtures;

use App\Entity\Department;
use App\Entity\LeaveRequest;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test verisi. Tüm kullanıcıların şifresi: Test1234
 *
 *  Yazılım ekibi:  yonetici@test.com (yönetici), calisan@test.com, calisan2@test.com
 *  İK ekibi:       ik.yonetici@test.com (yönetici), ik.calisan@test.com
 */
class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $software = (new Department())->setName('Yazılım');
        $hr = (new Department())->setName('İnsan Kaynakları');
        $manager->persist($software);
        $manager->persist($hr);

        $softwareManager = $this->createUser($manager, 'yonetici@test.com', ['ROLE_ADMIN'], $software, 14);
        $employee = $this->createUser($manager, 'calisan@test.com', ['ROLE_USER'], $software, 14);
        $employee2 = $this->createUser($manager, 'calisan2@test.com', ['ROLE_USER'], $software, 3);
        $this->createUser($manager, 'ik.yonetici@test.com', ['ROLE_ADMIN'], $hr, 14);
        $hrEmployee = $this->createUser($manager, 'ik.calisan@test.com', ['ROLE_USER'], $hr, 14);

        // Örnek talepler
        $this->createLeave($manager, $employee, '2026-12-07', '2026-12-11', 'Yıllık izin', LeaveRequest::STATUS_PENDING);
        $this->createLeave($manager, $employee2, '2026-12-14', '2026-12-15', 'Ailevi sebepler', LeaveRequest::STATUS_PENDING);
        $this->createLeave($manager, $hrEmployee, '2026-12-21', '2026-12-23', 'Yıllık izin', LeaveRequest::STATUS_PENDING);

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, array $roles, Department $department, int $balance): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setDepartment($department);
        $user->setLeaveBalance($balance);
        $user->setPassword($this->hasher->hashPassword($user, 'Test1234'));
        $manager->persist($user);

        return $user;
    }

    private function createLeave(ObjectManager $manager, User $user, string $start, string $end, string $reason, string $status): void
    {
        $leave = new LeaveRequest();
        $leave->setUser($user);
        $leave->setStartDate(new \DateTime($start));
        $leave->setEndDate(new \DateTime($end));
        $leave->setReason($reason);
        $leave->setStatus($status);
        $manager->persist($leave);
    }
}
