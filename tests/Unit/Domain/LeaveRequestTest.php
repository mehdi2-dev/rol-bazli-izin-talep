<?php

namespace App\Tests\Unit\Domain;

use App\Domain\Exception\AccessDenied;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\ValidationFailed;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\Department;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

/** İzin talebinin yaşam döngüsü kuralları, veritabanı olmadan. */
class LeaveRequestTest extends TestCase
{
    private const NOW = '2030-01-01 09:00';  // salı

    private Department $software;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        $this->software = new Department('Yazılım');
        $this->manager = User::create('yonetici@test.com', 'hash', $this->software, 14, [User::ROLE_MANAGER]);
        $this->employee = User::create('calisan@test.com', 'hash', $this->software, 14);
    }

    private function submit(string $start = '2030-01-07', string $end = '2030-01-11', bool $overlaps = false, ?User $employee = null): LeaveRequest
    {
        return LeaveRequest::submit(
            $employee ?? $this->employee,
            new LeavePeriod(new \DateTimeImmutable($start), new \DateTimeImmutable($end)),
            'Tatil',
            new \DateTimeImmutable(self::NOW),
            $overlaps,
        );
    }

    public function testSubmitCreatesPendingRequest(): void
    {
        $leave = $this->submit();

        self::assertSame(LeaveStatus::Pending, $leave->status());
        self::assertSame(5, $leave->workingDays());
        self::assertSame(14, $this->employee->leaveBalance(), 'talep bakiyeyi değiştirmez');
    }

    public function testStartingTodayIsAllowed(): void
    {
        self::assertSame(LeaveStatus::Pending, $this->submit('2030-01-01', '2030-01-01')->status());
    }

    public function testPastDateIsRejected(): void
    {
        $this->expectExceptionObject(new ValidationFailed('Geçmiş tarihli izin talebi oluşturulamaz.'));

        $this->submit('2029-12-31', '2030-01-02');
    }

    public function testWeekendOnlyIsRejected(): void
    {
        $this->expectException(ValidationFailed::class);
        $this->expectExceptionMessage('iş günü yok');

        $this->submit('2030-01-05', '2030-01-06');
    }

    public function testInsufficientBalanceIsRejected(): void
    {
        $this->expectExceptionObject(new ValidationFailed('Yetersiz izin bakiyesi. Talep: 5 gün, kalan: 3 gün.'));

        $this->submit(employee: User::create('az@test.com', 'hash', $this->software, 3));
    }

    public function testOverlapIsAConflict(): void
    {
        $this->expectException(Conflict::class);

        $this->submit(overlaps: true);
    }

    public function testBlankReasonIsRejected(): void
    {
        $this->expectException(ValidationFailed::class);

        LeaveRequest::submit(
            $this->employee,
            new LeavePeriod(new \DateTimeImmutable('2030-01-07'), new \DateTimeImmutable('2030-01-07')),
            '   ',
            new \DateTimeImmutable(self::NOW),
            false,
        );
    }

    public function testApproveDeductsBalance(): void
    {
        $leave = $this->submit();

        $leave->approve($this->manager);

        self::assertSame(LeaveStatus::Approved, $leave->status());
        self::assertSame(9, $this->employee->leaveBalance());
    }

    public function testApproveFailsWhenBalanceDroppedMeanwhile(): void
    {
        $leave = $this->submit();
        $this->employee->changeLeaveBalance(2);

        try {
            $leave->approve($this->manager);
            self::fail('ValidationFailed bekleniyordu');
        } catch (ValidationFailed $e) {
            self::assertStringContainsString('bakiyesi yetersiz', $e->getMessage());
        }

        self::assertSame(LeaveStatus::Pending, $leave->status(), 'başarısız onay durumu değiştirmez');
        self::assertSame(2, $this->employee->leaveBalance());
    }

    public function testRejectRequiresReasonAndKeepsBalance(): void
    {
        $leave = $this->submit();

        try {
            $leave->reject($this->manager, ' ');
            self::fail('ValidationFailed bekleniyordu');
        } catch (ValidationFailed) {
        }

        $leave->reject($this->manager, 'Yoğun dönem');

        self::assertSame(LeaveStatus::Rejected, $leave->status());
        self::assertSame('Yoğun dönem', $leave->rejectionReason());
        self::assertSame(14, $this->employee->leaveBalance());
    }

    public function testDecidedRequestCannotBeDecidedAgain(): void
    {
        $leave = $this->submit();
        $leave->approve($this->manager);

        $this->expectExceptionObject(new ValidationFailed('Bu talep zaten sonuçlandırılmış.'));

        $leave->reject($this->manager, 'Vazgeçtim');
    }

    public function testManagerCannotDecideOwnRequest(): void
    {
        $leave = $this->submit(employee: $this->manager);

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessage('Kendi izin talebinizi');

        $leave->approve($this->manager);
    }

    public function testManagerCannotDecideOtherTeam(): void
    {
        $hrManager = User::create('ik@test.com', 'hash', new Department('İK'), 14, [User::ROLE_MANAGER]);
        $leave = $this->submit();

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessage('ekibinize ait değil');

        $leave->approve($hrManager);
    }

    public function testManagerWithoutTeamCannotDecide(): void
    {
        $leave = $this->submit();

        $this->expectException(AccessDenied::class);

        $leave->approve(User::create('ekipsiz@test.com', 'hash', null, 14, [User::ROLE_MANAGER]));
    }
}
