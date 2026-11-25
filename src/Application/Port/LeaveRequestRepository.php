<?php

namespace App\Application\Port;

use App\Domain\Exception\NotFound;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\Department;
use App\Domain\User\User;

/** Çıkan port: izin taleplerinin saklandığı yer (Doctrine, bellek, …). */
interface LeaveRequestRepository
{
    /** @throws NotFound */
    public function get(int $id): LeaveRequest;

    /** @return LeaveRequest[] en yeni başlangıç tarihi önce */
    public function findByEmployee(User $employee): array;

    /** @return LeaveRequest[] ekibin talepleri, başlangıç tarihine göre */
    public function findByDepartment(Department $department, ?LeaveStatus $status = null): array;

    /** Çalışanın bu aralıkla çakışan bekleyen veya onaylanmış talebi var mı? */
    public function hasOverlapping(User $employee, LeavePeriod $period): bool;

    /** Talebi ve ilişkili değişiklikleri (ör. bakiye) kalıcı hale getirir. */
    public function save(LeaveRequest $leaveRequest): void;
}
