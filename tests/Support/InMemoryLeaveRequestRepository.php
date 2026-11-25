<?php

namespace App\Tests\Support;

use App\Application\Port\LeaveRequestRepository;
use App\Domain\Exception\NotFound;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\Department;
use App\Domain\User\User;

/**
 * Test adaptörü: LeaveRequestRepository portunu bellekte uygular.
 * Use case'ler veritabanı olmadan, milisaniyeler içinde test edilebilir.
 */
final class InMemoryLeaveRequestRepository implements LeaveRequestRepository
{
    /** @var array<int, LeaveRequest> */
    private array $items = [];
    private int $nextId = 1;

    public function get(int $id): LeaveRequest
    {
        return $this->items[$id] ?? throw new NotFound('İzin talebi bulunamadı.');
    }

    public function findByEmployee(User $employee): array
    {
        return array_values(array_filter($this->items, fn (LeaveRequest $l) => $l->employee()->sameAs($employee)));
    }

    public function findByDepartment(Department $department, ?LeaveStatus $status = null): array
    {
        return array_values(array_filter(
            $this->items,
            fn (LeaveRequest $l) => $department->equals($l->employee()->department())
                && ($status === null || $l->status() === $status),
        ));
    }

    public function hasOverlapping(User $employee, LeavePeriod $period): bool
    {
        foreach ($this->findByEmployee($employee) as $leave) {
            if ($leave->status()->blocksCalendar() && $leave->period()->overlaps($period)) {
                return true;
            }
        }

        return false;
    }

    public function save(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->id() === null) {
            Ids::assign($leaveRequest, $this->nextId++);
        }

        $this->items[$leaveRequest->id()] = $leaveRequest;
    }
}
