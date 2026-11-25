<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\UseCase\User\AddTeamMember;
use App\Application\UseCase\User\ChangeLeaveBalance;
use App\Application\UseCase\User\ListTeamMembers;
use App\Domain\User\User;
use App\Infrastructure\Http\Presenter\UserPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Giren adaptör: oturum bilgisi ve yöneticinin ekip yönetimi. */
#[Route('/api')]
final class UserController extends ApiController
{
    private const BALANCE_ERROR = 'İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.';

    #[Route('/me', name: 'app_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        return $this->json(UserPresenter::toArray($this->currentUser()));
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(ListTeamMembers $listTeamMembers): JsonResponse
    {
        return $this->json(UserPresenter::list($listTeamMembers($this->currentUser())));
    }

    #[Route('/admin/users', name: 'app_admin_create_user', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, AddTeamMember $addTeamMember): JsonResponse
    {
        $body = $this->body($request);
        $balance = $body['leaveBalance'] ?? User::DEFAULT_BALANCE;

        if (!is_int($balance)) {
            return $this->json(['error' => self::BALANCE_ERROR], 400);
        }

        $user = $addTeamMember(
            $this->currentUser(),
            (string) ($body['email'] ?? ''),
            (string) ($body['password'] ?? ''),
            $balance,
        );

        return $this->json([
            'message' => 'Kullanıcı oluşturuldu.',
            'user' => UserPresenter::toArray($user),
        ], 201);
    }

    #[Route('/admin/users/{id}', name: 'app_admin_update_user', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request, ChangeLeaveBalance $changeLeaveBalance): JsonResponse
    {
        $balance = $this->body($request)['leaveBalance'] ?? null;

        if (!is_int($balance)) {
            return $this->json(['error' => self::BALANCE_ERROR], 400);
        }

        $user = $changeLeaveBalance($this->currentUser(), $id, $balance);

        return $this->json([
            'message' => 'Kullanıcı güncellendi.',
            'user' => UserPresenter::toArray($user),
        ]);
    }
}
