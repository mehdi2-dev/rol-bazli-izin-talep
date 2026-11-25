<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\UseCase\Leave\ApproveLeaveRequest;
use App\Application\UseCase\Leave\ListMyLeaves;
use App\Application\UseCase\Leave\ListTeamLeaves;
use App\Application\UseCase\Leave\RejectLeaveRequest;
use App\Application\UseCase\Leave\SubmitLeaveRequest;
use App\Domain\Leave\LeaveStatus;
use App\Infrastructure\Http\Presenter\LeavePresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Giren adaptör: izin talepleri HTTP API'si.
 * Yalnızca girdi biçimini doğrular; kurallar use case ve domain'dedir.
 * Kural ihlalleri (Domain\Exception) ApiSubscriber tarafından JSON hataya çevrilir.
 */
#[Route('/api')]
final class LeaveRequestController extends ApiController
{
    #[Route('/leaves', name: 'app_my_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(ListMyLeaves $listMyLeaves): JsonResponse
    {
        return $this->json(LeavePresenter::list($listMyLeaves($this->currentUser())));
    }

    #[Route('/leaves', name: 'app_create_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, SubmitLeaveRequest $submit): JsonResponse
    {
        $body = $this->body($request);
        $reason = trim((string) ($body['reason'] ?? ''));

        if (empty($body['startDate']) || empty($body['endDate']) || $reason === '') {
            return $this->json(['error' => 'Eksik alanlar var (startDate, endDate, reason gerekli).'], 400);
        }

        $start = self::parseDate($body['startDate']);
        $end = self::parseDate($body['endDate']);
        if ($start === null || $end === null) {
            return $this->json(['error' => 'Tarih formatı geçersiz. Beklenen format: YYYY-MM-DD.'], 400);
        }

        $leave = $submit($this->currentUser(), $start, $end, $reason);

        return $this->json([
            'message' => 'İzin talebi başarıyla oluşturuldu.',
            'id' => $leave->id(),
            'leave' => LeavePresenter::toArray($leave),
        ], 201);
    }

    #[Route('/admin/leaves/pending', name: 'app_admin_pending_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function pending(ListTeamLeaves $listTeamLeaves): JsonResponse
    {
        return $this->json(LeavePresenter::list($listTeamLeaves($this->currentUser(), LeaveStatus::Pending)));
    }

    /** ?status=approved gibi filtrelenebilir; bilinmeyen durum boş liste döner. */
    #[Route('/admin/leaves', name: 'app_admin_team_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function team(Request $request, ListTeamLeaves $listTeamLeaves): JsonResponse
    {
        $statusRaw = (string) $request->query->get('status', '');
        $status = LeaveStatus::tryFrom($statusRaw);

        if ($statusRaw !== '' && $status === null) {
            return $this->json([]);
        }

        return $this->json(LeavePresenter::list($listTeamLeaves($this->currentUser(), $status)));
    }

    #[Route('/admin/leaves/{id}/approve', name: 'app_admin_approve_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(int $id, ApproveLeaveRequest $approve): JsonResponse
    {
        $leave = $approve($this->currentUser(), $id);

        return $this->json([
            'message' => 'İzin talebi onaylandı.',
            'leave' => LeavePresenter::toArray($leave),
            'remainingBalance' => $leave->employee()->leaveBalance(),
        ]);
    }

    #[Route('/admin/leaves/{id}/reject', name: 'app_admin_reject_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(int $id, Request $request, RejectLeaveRequest $reject): JsonResponse
    {
        $leave = $reject($this->currentUser(), $id, (string) ($this->body($request)['reason'] ?? ''));

        return $this->json([
            'message' => 'İzin talebi reddedildi.',
            'leave' => LeavePresenter::toArray($leave),
        ]);
    }

    /** Yalnızca YYYY-MM-DD kabul edilir; 2030-02-31 gibi taşan tarihler reddedilir. */
    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
