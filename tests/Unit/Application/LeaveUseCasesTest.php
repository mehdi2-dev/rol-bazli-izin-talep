<?php

namespace App\Tests\Unit\Application;

use App\Application\UseCase\Leave\ApproveLeaveRequest;
use App\Application\UseCase\Leave\ListMyLeaves;
use App\Application\UseCase\Leave\ListTeamLeaves;
use App\Application\UseCase\Leave\RejectLeaveRequest;
use App\Application\UseCase\Leave\SubmitLeaveRequest;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\NotFound;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\Department;
use App\Domain\User\User;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryLeaveRequestRepository;
use App\Tests\Support\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * İzin use case'leri, gerçek veritabanı yerine bellek içi adaptörlerle.
 * Hexagonal mimarinin faydası: aynı use case'ler Doctrine yerine burada sahte portlarla çalışır.
 */
class LeaveUseCasesTest extends TestCase
{
    private InMemoryLeaveRequestRepository $leaves;
    private User $manager;
    private User $employee;
    private User $hrEmployee;

    protected function setUp(): void
    {
        $this->leaves = new InMemoryLeaveRequestRepository();

        $users = new InMemoryUserRepository();
        $software = new Department('Yazılım');
        $this->manager = User::create('yonetici@test.com', 'h', $software, 14, [User::ROLE_MANAGER]);
        $this->employee = User::create('calisan@test.com', 'h', $software, 14);
        $this->hrEmployee = User::create('ik@test.com', 'h', new Department('İK'), 14);
        foreach ([$this->manager, $this->employee, $this->hrEmployee] as $user) {
            $users->save($user);
        }
    }

    private function submit(User $employee, string $start = '2030-01-07', string $end = '2030-01-11')
    {
        return (new SubmitLeaveRequest($this->leaves, new FixedClock('2030-01-01 09:00')))(
            $employee,
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            'Tatil',
        );
    }

    public function testSubmitSavesPendingRequest(): void
    {
        $leave = $this->submit($this->employee);

        self::assertNotNull($leave->id());
        self::assertSame($leave, $this->leaves->get($leave->id()));
        self::assertSame(LeaveStatus::Pending, $leave->status());
    }

    public function testSubmitDetectsOverlapThroughThePort(): void
    {
        $this->submit($this->employee);

        $this->expectException(Conflict::class);

        $this->submit($this->employee, '2030-01-09', '2030-01-09');
    }

    public function testListMyLeavesShowsOnlyOwn(): void
    {
        $this->submit($this->employee);
        $this->submit($this->hrEmployee);

        $mine = (new ListMyLeaves($this->leaves))($this->employee);

        self::assertCount(1, $mine);
        self::assertSame($this->employee, $mine[0]->employee());
    }

    public function testListTeamLeavesShowsOnlyManagersTeam(): void
    {
        $this->submit($this->employee);
        $this->submit($this->hrEmployee);

        $team = (new ListTeamLeaves($this->leaves))($this->manager, LeaveStatus::Pending);

        self::assertSame(['calisan@test.com'], array_map(fn ($l) => $l->employee()->email(), $team));
    }

    public function testManagerWithoutTeamSeesNothing(): void
    {
        $this->submit($this->employee);

        self::assertSame([], (new ListTeamLeaves($this->leaves))(User::create('x@test.com', 'h', null)));
    }

    public function testApproveAndReject(): void
    {
        $first = $this->submit($this->employee);
        $second = $this->submit($this->employee, '2030-01-14', '2030-01-14');

        (new ApproveLeaveRequest($this->leaves))($this->manager, $first->id());
        (new RejectLeaveRequest($this->leaves))($this->manager, $second->id(), 'Yoğun dönem');

        self::assertSame(LeaveStatus::Approved, $first->status());
        self::assertSame(LeaveStatus::Rejected, $second->status());
        self::assertSame(9, $this->employee->leaveBalance());
    }

    public function testApproveUnknownRequest(): void
    {
        $this->expectException(NotFound::class);

        (new ApproveLeaveRequest($this->leaves))($this->manager, 42);
    }
}
