<?php

namespace App\Domain\Leave;

use App\Domain\Exception\AccessDenied;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\ValidationFailed;
use App\Domain\User\User;

/**
 * İzin talebi ve yaşam döngüsü: bekliyor → onaylandı | reddedildi.
 *
 * Tüm iş kuralları burada; controller veya veritabanı bilgisi yok.
 */
class LeaveRequest
{
    private ?int $id = null;
    private LeaveStatus $status = LeaveStatus::Pending;
    private ?string $rejectionReason = null;

    private function __construct(
        private User $employee,
        private LeavePeriod $period,
        private string $reason,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Çalışan yeni bir talep oluşturur.
     *
     * @param bool $overlapsExisting Çalışanın bu aralıkla çakışan bekleyen/onaylı talebi var mı
     *                               (bunu veritabanına sorabilen katman hesaplar)
     */
    public static function submit(
        User $employee,
        LeavePeriod $period,
        string $reason,
        \DateTimeImmutable $now,
        bool $overlapsExisting,
    ): self {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationFailed('İzin sebebi boş olamaz.');
        }

        if ($period->startsBefore($now)) {
            throw new ValidationFailed('Geçmiş tarihli izin talebi oluşturulamaz.');
        }

        $days = $period->workingDays();
        if ($days === 0) {
            throw new ValidationFailed('Seçilen aralıkta iş günü yok (yalnızca hafta sonu).');
        }

        if (!$employee->hasBalanceFor($days)) {
            throw new ValidationFailed(sprintf(
                'Yetersiz izin bakiyesi. Talep: %d gün, kalan: %d gün.',
                $days,
                $employee->leaveBalance(),
            ));
        }

        if ($overlapsExisting) {
            throw new Conflict('Bu tarihlerle çakışan bekleyen veya onaylanmış bir izin talebiniz var.');
        }

        return new self($employee, $period, $reason, $now);
    }

    /** Yönetici onaylar; iş günleri çalışanın bakiyesinden düşülür. */
    public function approve(User $manager): void
    {
        $this->assertCanBeDecidedBy($manager);

        $this->employee->deductLeave($this->workingDays());
        $this->status = LeaveStatus::Approved;
    }

    /** Yönetici reddeder; açıklama zorunludur, bakiye değişmez. */
    public function reject(User $manager, string $reason): void
    {
        $this->assertCanBeDecidedBy($manager);

        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationFailed('Ret işleminde açıklama zorunludur.');
        }

        $this->status = LeaveStatus::Rejected;
        $this->rejectionReason = $reason;
    }

    /** Yönetici yalnızca kendi ekibinin, başkasına ait, bekleyen taleplerine karar verebilir. */
    private function assertCanBeDecidedBy(User $manager): void
    {
        if ($this->employee->sameAs($manager)) {
            throw new AccessDenied('Kendi izin talebinizi onaylayamaz veya reddedemezsiniz.');
        }

        if (!$manager->isInSameTeamAs($this->employee)) {
            throw new AccessDenied('Bu talep sizin ekibinize ait değil.');
        }

        if ($this->status !== LeaveStatus::Pending) {
            throw new ValidationFailed('Bu talep zaten sonuçlandırılmış.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function employee(): User
    {
        return $this->employee;
    }

    public function period(): LeavePeriod
    {
        return $this->period;
    }

    public function workingDays(): int
    {
        return $this->period->workingDays();
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): LeaveStatus
    {
        return $this->status;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
