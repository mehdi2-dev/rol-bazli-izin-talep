<?php

namespace App\Tests\Unit\Domain;

use App\Domain\Exception\ValidationFailed;
use App\Domain\User\Department;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testEveryUserIsAnEmployee(): void
    {
        $manager = User::create('y@test.com', 'hash', null, 14, [User::ROLE_MANAGER]);

        self::assertSame([User::ROLE_MANAGER, User::ROLE_EMPLOYEE], $manager->roles());
        self::assertTrue($manager->isManager());
        self::assertFalse(User::create('c@test.com', 'hash', null)->isManager());
    }

    public function testDefaultBalance(): void
    {
        self::assertSame(14, User::create('c@test.com', 'hash', null)->leaveBalance());
    }

    public function testBalanceMustBeInRange(): void
    {
        foreach ([-1, 61] as $invalid) {
            try {
                User::create('c@test.com', 'hash', null, $invalid);
                self::fail("$invalid kabul edilmemeliydi");
            } catch (ValidationFailed $e) {
                self::assertSame('İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.', $e->getMessage());
            }
        }

        $user = User::create('c@test.com', 'hash', null, 0);
        $user->changeLeaveBalance(60);
        self::assertSame(60, $user->leaveBalance());
    }

    public function testDeductLeave(): void
    {
        $user = User::create('c@test.com', 'hash', null, 5);

        $user->deductLeave(5);
        self::assertSame(0, $user->leaveBalance());

        $this->expectException(ValidationFailed::class);
        $user->deductLeave(1);
    }

    public function testTeamMembership(): void
    {
        $software = new Department('Yazılım');
        $a = User::create('a@test.com', 'hash', $software);
        $b = User::create('b@test.com', 'hash', $software);
        $other = User::create('o@test.com', 'hash', new Department('İK'));
        $noTeam = User::create('n@test.com', 'hash', null);

        self::assertTrue($a->isInSameTeamAs($b));
        self::assertFalse($a->isInSameTeamAs($other));
        self::assertFalse($a->isInSameTeamAs($noTeam));
        self::assertFalse($noTeam->isInSameTeamAs($noTeam), 'ekipsiz kullanıcının ekibi yoktur');
    }
}
