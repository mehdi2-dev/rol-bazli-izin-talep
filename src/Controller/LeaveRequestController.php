<?php

namespace App\Controller;

use App\Application\Port\Clock;
use App\Application\Port\LeaveRequestRepository;
use App\Domain\Leave\LeavePeriod;
use App\Domain\Leave\LeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Domain\User\User;
use App\Infrastructure\Http\Presenter\LeavePresenter;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ROL-24 ara durum: iş kuralları artık Domain\Leave\LeaveRequest içinde.
 * Kural ihlalleri domain exception olarak fırlar; ApiSubscriber bunları JSON hataya çevirir.
 * ROL-25'te bu sınıf use case'leri çağıran ince bir adaptöre dönüşecek.
 */
#[Route('/api')]
class LeaveRequestController extends AbstractController
{
    public function __construct(
        private LeaveRequestRepository $leaves,
        private Clock $clock,
    ) {
    }

    #[Route('/leaves', name: 'app_my_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyLeaves(): JsonResponse
    {
        return $this->json(LeavePresenter::list($this->leaves->findByEmployee($this->currentUser())));
    }

    #[Route('/leaves', name: 'app_create_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createLeave(Request $request): JsonResponse
    {
        $employee = $this->currentUser();
        $parameters = json_decode($request->getContent(), true) ?? [];

        $startRaw = $parameters['startDate'] ?? null;
        $endRaw = $parameters['endDate'] ?? null;
        $reason = trim((string) ($parameters['reason'] ?? ''));

        if (!$startRaw || !$endRaw || $reason === '') {
            return $this->json(['error' => 'Eksik alanlar var (startDate, endDate, reason gerekli).'], 400);
        }

        $startDate = $this->parseDate($startRaw);
        $endDate = $this->parseDate($endRaw);

        if (!$startDate || !$endDate) {
            return $this->json(['error' => 'Tarih formatı geçersiz. Beklenen format: YYYY-MM-DD.'], 400);
        }

        $period = new LeavePeriod($startDate, $endDate);
        $leave = LeaveRequest::submit(
            $employee,
            $period,
            $reason,
            $this->clock->now(),
            $this->leaves->hasOverlapping($employee, $period),
        );
        $this->leaves->save($leave);

        return $this->json([
            'message' => 'İzin talebi başarıyla oluşturuldu.',
            'id' => $leave->id(),
            'leave' => LeavePresenter::toArray($leave),
        ], 201);
    }

    #[Route('/admin/leaves/pending', name: 'app_admin_pending_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getPendingLeaves(): JsonResponse
    {
        $department = $this->currentUser()->department();

        return $this->json($department === null ? [] : LeavePresenter::list(
            $this->leaves->findByDepartment($department, LeaveStatus::Pending),
        ));
    }

    /** Ekibin tüm talepleri (geçmiş dahil). ?status=approved gibi filtrelenebilir. */
    #[Route('/admin/leaves', name: 'app_admin_team_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getTeamLeaves(Request $request): JsonResponse
    {
        $department = $this->currentUser()->department();
        if ($department === null) {
            return $this->json([]);
        }

        $statusRaw = $request->query->get('status');
        $status = $statusRaw ? LeaveStatus::tryFrom($statusRaw) : null;
        if ($statusRaw && $status === null) {
            return $this->json([]);  // bilinmeyen durum: eşleşen talep yok
        }

        return $this->json(LeavePresenter::list($this->leaves->findByDepartment($department, $status)));
    }

    #[Route('/admin/leaves/{id}/approve', name: 'app_admin_approve_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(int $id): JsonResponse
    {
        $leave = $this->leaves->get($id);
        $leave->approve($this->currentUser());
        $this->leaves->save($leave);

        return $this->json([
            'message' => 'İzin talebi onaylandı.',
            'leave' => LeavePresenter::toArray($leave),
            'remainingBalance' => $leave->employee()->leaveBalance(),
        ]);
    }

    #[Route('/admin/leaves/{id}/reject', name: 'app_admin_reject_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(int $id, Request $request): JsonResponse
    {
        $parameters = json_decode($request->getContent(), true) ?? [];

        $leave = $this->leaves->get($id);
        $leave->reject($this->currentUser(), (string) ($parameters['reason'] ?? ''));
        $this->leaves->save($leave);

        return $this->json([
            'message' => 'İzin talebi reddedildi.',
            'leave' => LeavePresenter::toArray($leave),
        ]);
    }

    private function currentUser(): User
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();

        return $securityUser->user();
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
