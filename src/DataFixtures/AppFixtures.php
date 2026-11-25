<?php

namespace App\DataFixtures;

use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\User\Department;
use App\Domain\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Test verisi. Tüm kullanıcıların şifresi: Test1234
 *
 *  Yazılım ekibi:  yonetici@test.com (yönetici), calisan@test.com, calisan2@test.com
 *  İK ekibi:       ik.yonetici@test.com (yönetici), ik.calisan@test.com
 *
 * Örnek talepler bugüne göre ileri tarihlidir; böylece fixture'lar zamanla eskimez.
 * Talepler domain kurallarından geçerek (LeaveRequest::submit) oluşturulur.
 */
class AppFixtures extends Fixture
{
    public const PASSWORD = 'Test1234';

    public function __construct(
        private PasswordHasher $hasher,
        private Clock $clock,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $software = new Department('Yazılım');
        $hr = new Department('İnsan Kaynakları');
        $manager->persist($software);
        $manager->persist($hr);

        $this->createUser($manager, 'yonetici@test.com', $software, 14, isManager: true);
        $employee = $this->createUser($manager, 'calisan@test.com', $software, 14);
        $employee2 = $this->createUser($manager, 'calisan2@test.com', $software, 3);
        $this->createUser($manager, 'ik.yonetici@test.com', $hr, 14, isManager: true);
        $hrEmployee = $this->createUser($manager, 'ik.calisan@test.com', $hr, 14);

        $monday = (new \DateTimeImmutable('monday next week'))->modify('+2 weeks');
        $this->createLeave($manager, $employee, $monday, 4, 'Yıllık izin');                       // 5 iş günü
        $this->createLeave($manager, $employee2, $monday->modify('+1 week'), 1, 'Ailevi sebepler'); // 2 iş günü
        $this->createLeave($manager, $hrEmployee, $monday->modify('+2 weeks'), 2, 'Yıllık izin');   // 3 iş günü

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, Department $department, int $balance, bool $isManager = false): User
    {
        $roles = $isManager ? [User::ROLE_MANAGER] : [User::ROLE_EMPLOYEE];
        $user = User::create($email, $this->hasher->hash(self::PASSWORD), $department, $balance, $roles);
        $manager->persist($user);

        return $user;
    }

    private function createLeave(ObjectManager $manager, User $employee, \DateTimeImmutable $start, int $extraDays, string $reason): void
    {
        $leave = LeaveRequest::submit(
            $employee,
            new LeavePeriod($start, $start->modify("+$extraDays days")),
            $reason,
            $this->clock->now(),
            overlapsExisting: false,
        );
        $manager->persist($leave);
    }
}
