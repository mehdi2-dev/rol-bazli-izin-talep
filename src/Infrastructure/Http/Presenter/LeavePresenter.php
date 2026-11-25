<?php

namespace App\Infrastructure\Http\Presenter;

use App\Domain\Leave\LeaveRequest;

/** Domain nesnesini API'nin JSON biçimine çevirir (eski LeaveRequest::toArray ile aynı alanlar). */
final class LeavePresenter
{
    /** @return array<string, mixed> */
    public static function toArray(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id(),
            'user' => $leave->employee()->email(),
            'startDate' => $leave->period()->startDate()->format('Y-m-d'),
            'endDate' => $leave->period()->endDate()->format('Y-m-d'),
            'days' => $leave->workingDays(),
            'reason' => $leave->reason(),
            'status' => $leave->status()->value,
            'rejectionReason' => $leave->rejectionReason(),
            'createdAt' => $leave->createdAt()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param LeaveRequest[] $leaves
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $leaves): array
    {
        return array_values(array_map(self::toArray(...), $leaves));
    }
}
