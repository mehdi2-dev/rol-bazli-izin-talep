<?php

namespace App\Tests\Unit\Application;

use App\Application\UseCase\User\AddTeamMember;
use App\Application\UseCase\User\ChangeLeaveBalance;
use App\Application\UseCase\User\ListTeamMembers;
use App\Domain\Exception\AccessDenied;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\NotFound;
use App\Domain\Exception\ValidationFailed;
use App\Domain\User\Department;
use App\Domain\User\User;
use App\Tests\Support\InMemoryUserRepository;
use App\Tests\Support\PlainPasswordHasher;
use PHPUnit\Framework\TestCase;

class UserUseCasesTest extends TestCase
{
    private InMemoryUserRepository $users;
    private AddTeamMember $addTeamMember;
    private User $manager;
    private User $hrEmployee;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->addTeamMember = new AddTeamMember($this->users, new PlainPasswordHasher());

        $this->manager = User::create('yonetici@test.com', 'h', new Department('Yazılım'), 14, [User::ROLE_MANAGER]);
        $this->hrEmployee = User::create('ik@test.com', 'h', new Department('İK'), 14);
        $this->users->save($this->manager);
        $this->users->save($this->hrEmployee);
    }

    public function testAddTeamMemberJoinsManagersTeam(): void
    {
        $user = ($this->addTeamMember)($this->manager, '  Yeni@Test.com ', 'Gizli123', 20);

        self::assertSame('yeni@test.com', $user->email());
        self::assertSame('hashed:Gizli123', $user->passwordHash(), 'şifre port üzerinden özetlenir');
        self::assertTrue($user->isInSameTeamAs($this->manager));
        self::assertFalse($user->isManager());
        self::assertSame($user, $this->users->findByEmail('yeni@test.com'));
    }

    public function testAddTeamMemberRules(): void
    {
        $cases = [
            ['gecersiz', 'Gizli123', 14, ValidationFailed::class],
            ['kisa@test.com', '123', 14, ValidationFailed::class],
            ['bakiye@test.com', 'Gizli123', 61, ValidationFailed::class],
            ['ik@test.com', 'Gizli123', 14, Conflict::class],
        ];

        foreach ($cases as [$email, $password, $balance, $expected]) {
            try {
                ($this->addTeamMember)($this->manager, $email, $password, $balance);
                self::fail("$email kabul edilmemeliydi");
            } catch (\Exception $e) {
                self::assertInstanceOf($expected, $e, $email);
            }
        }
    }

    public function testListTeamMembers(): void
    {
        ($this->addTeamMember)($this->manager, 'b@test.com', 'Gizli123');
        ($this->addTeamMember)($this->manager, 'a@test.com', 'Gizli123');

        $emails = array_map(fn (User $u) => $u->email(), (new ListTeamMembers($this->users))($this->manager));

        self::assertSame(['a@test.com', 'b@test.com', 'yonetici@test.com'], $emails);
    }

    public function testChangeLeaveBalance(): void
    {
        $user = ($this->addTeamMember)($this->manager, 'c@test.com', 'Gizli123');

        (new ChangeLeaveBalance($this->users))($this->manager, $user->id(), 30);

        self::assertSame(30, $user->leaveBalance());
    }

    public function testChangeLeaveBalanceOfOtherTeamIsDenied(): void
    {
        $this->expectException(AccessDenied::class);

        (new ChangeLeaveBalance($this->users))($this->manager, $this->hrEmployee->id(), 30);
    }

    public function testChangeLeaveBalanceOfUnknownUser(): void
    {
        $this->expectException(NotFound::class);

        (new ChangeLeaveBalance($this->users))($this->manager, 999, 30);
    }
}
