<?php

namespace App\Controller;

use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Repository\LeaveRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class LeaveRequestController extends AbstractController
{
    // ---------------------------------------------------------------
    // ÇALIŞAN: kendi izinleri (ROL-9)
    // ---------------------------------------------------------------

    /** Kural: Çalışan sadece kendi izinlerini görebilir. */
    #[Route('/leaves', name: 'app_my_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMyLeaves(LeaveRequestRepository $leaveRequestRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $leaves = $leaveRequestRepository->findBy(['user' => $user], ['startDate' => 'DESC']);

        return $this->json(array_map(fn (LeaveRequest $l) => $l->toArray(), $leaves));
    }

    #[Route('/leaves', name: 'app_create_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createLeave(
        Request $request,
        EntityManagerInterface $entityManager,
        LeaveRequestRepository $leaveRequestRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
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

        if ($endDate < $startDate) {
            return $this->json(['error' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.'], 400);
        }

        $today = new \DateTime('today');
        if ($startDate < $today) {
            return $this->json(['error' => 'Geçmiş tarihli izin talebi oluşturulamaz.'], 400);
        }

        $days = LeaveRequest::countWorkingDays($startDate, $endDate);
        if ($days === 0) {
            return $this->json(['error' => 'Seçilen aralıkta iş günü yok (yalnızca hafta sonu).'], 400);
        }

        if ($days > $user->getLeaveBalance()) {
            return $this->json([
                'error' => sprintf('Yetersiz izin bakiyesi. Talep: %d gün, kalan: %d gün.', $days, $user->getLeaveBalance()),
            ], 400);
        }

        if ($leaveRequestRepository->findOverlapping($user, $startDate, $endDate)) {
            return $this->json(['error' => 'Bu tarihlerle çakışan bekleyen veya onaylanmış bir izin talebiniz var.'], 409);
        }

        $leaveRequest = new LeaveRequest();
        $leaveRequest->setUser($user);
        $leaveRequest->setStartDate($startDate);
        $leaveRequest->setEndDate($endDate);
        $leaveRequest->setReason($reason);
        $leaveRequest->setStatus(LeaveRequest::STATUS_PENDING);

        $entityManager->persist($leaveRequest);
        $entityManager->flush();

        return $this->json([
            'message' => 'İzin talebi başarıyla oluşturuldu.',
            'id' => $leaveRequest->getId(),
            'leave' => $leaveRequest->toArray(),
        ], 201);
    }

    // ---------------------------------------------------------------
    // YÖNETİCİ: ekibin izinleri, onay / ret (ROL-10)
    // ---------------------------------------------------------------

    /** Kural: Yönetici yalnızca kendi ekibinin (departmanının) bekleyen taleplerini görür. */
    #[Route('/admin/leaves/pending', name: 'app_admin_pending_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getPendingLeaves(LeaveRequestRepository $leaveRequestRepository): JsonResponse
    {
        /** @var User $manager */
        $manager = $this->getUser();

        if (!$manager->getDepartment()) {
            return $this->json([]);
        }

        $leaves = $leaveRequestRepository->findByDepartment($manager->getDepartment(), LeaveRequest::STATUS_PENDING);

        return $this->json(array_map(fn (LeaveRequest $l) => $l->toArray(), $leaves));
    }

    /** Ekibin tüm talepleri (geçmiş dahil). ?status=approved gibi filtrelenebilir. */
    #[Route('/admin/leaves', name: 'app_admin_team_leaves', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getTeamLeaves(Request $request, LeaveRequestRepository $leaveRequestRepository): JsonResponse
    {
        /** @var User $manager */
        $manager = $this->getUser();

        if (!$manager->getDepartment()) {
            return $this->json([]);
        }

        $status = $request->query->get('status');
        $leaves = $leaveRequestRepository->findByDepartment($manager->getDepartment(), $status ?: null);

        return $this->json(array_map(fn (LeaveRequest $l) => $l->toArray(), $leaves));
    }

    #[Route('/admin/leaves/{id}/approve', name: 'app_admin_approve_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $leaveRequest = $entityManager->getRepository(LeaveRequest::class)->find($id);

        $error = $this->checkManagerCanDecide($leaveRequest);
        if ($error) {
            return $error;
        }

        $employee = $leaveRequest->getUser();
        $days = $leaveRequest->getWorkingDays();

        if ($days > $employee->getLeaveBalance()) {
            return $this->json([
                'error' => sprintf('Çalışanın bakiyesi yetersiz. Talep: %d gün, kalan: %d gün.', $days, $employee->getLeaveBalance()),
            ], 400);
        }

        $employee->setLeaveBalance($employee->getLeaveBalance() - $days);
        $leaveRequest->setStatus(LeaveRequest::STATUS_APPROVED);
        $entityManager->flush();

        return $this->json([
            'message' => 'İzin talebi onaylandı.',
            'leave' => $leaveRequest->toArray(),
            'remainingBalance' => $employee->getLeaveBalance(),
        ]);
    }

    /** Kural: Reddedilen izinlerde açıklama girilmelidir. */
    #[Route('/admin/leaves/{id}/reject', name: 'app_admin_reject_leave', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $leaveRequest = $entityManager->getRepository(LeaveRequest::class)->find($id);

        $error = $this->checkManagerCanDecide($leaveRequest);
        if ($error) {
            return $error;
        }

        $parameters = json_decode($request->getContent(), true) ?? [];
        $reason = trim((string) ($parameters['reason'] ?? ''));

        if ($reason === '') {
            return $this->json(['error' => 'Ret işleminde açıklama zorunludur.'], 400);
        }

        $leaveRequest->setStatus(LeaveRequest::STATUS_REJECTED);
        $leaveRequest->setRejectionReason($reason);
        $entityManager->flush();

        return $this->json([
            'message' => 'İzin talebi reddedildi.',
            'leave' => $leaveRequest->toArray(),
        ]);
    }

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    /**
     * Kural: Yönetici sadece kendi ekibinin izinlerini onaylayabilir/reddedebilir.
     * Sorun yoksa null, varsa hata cevabı döner.
     */
    private function checkManagerCanDecide(?LeaveRequest $leaveRequest): ?JsonResponse
    {
        if (!$leaveRequest) {
            return $this->json(['error' => 'İzin talebi bulunamadı.'], 404);
        }

        /** @var User $manager */
        $manager = $this->getUser();
        $employee = $leaveRequest->getUser();

        if ($employee === $manager) {
            return $this->json(['error' => 'Kendi izin talebinizi onaylayamaz veya reddedemezsiniz.'], 403);
        }

        $managerDept = $manager->getDepartment();
        if (!$managerDept || $employee->getDepartment()?->getId() !== $managerDept->getId()) {
            return $this->json(['error' => 'Bu talep sizin ekibinize ait değil.'], 403);
        }

        if ($leaveRequest->getStatus() !== LeaveRequest::STATUS_PENDING) {
            return $this->json(['error' => 'Bu talep zaten sonuçlandırılmış.'], 400);
        }

        return null;
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if (!is_string($value)) {
            return null;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        $errors = \DateTime::getLastErrors();

        if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
