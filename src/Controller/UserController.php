<?php

namespace App\Controller;

use App\Application\Port\PasswordHasher;
use App\Application\Port\UserRepository;
use App\Domain\Exception\AccessDenied;
use App\Domain\User\User;
use App\Infrastructure\Http\Presenter\UserPresenter;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** ROL-24 ara durum: domain User ve portlar kullanılıyor; ROL-25'te use case'lere taşınacak. */
#[Route('/api')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {
    }

    #[Route('/me', name: 'app_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        return $this->json(UserPresenter::toArray($this->currentUser()));
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(): JsonResponse
    {
        $department = $this->currentUser()->department();

        return $this->json($department === null ? [] : UserPresenter::list($this->users->findByDepartment($department)));
    }

    #[Route('/admin/users', name: 'app_admin_create_user', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $manager = $this->currentUser();
        $parameters = json_decode($request->getContent(), true) ?? [];

        $email = strtolower(trim((string) ($parameters['email'] ?? '')));
        $password = (string) ($parameters['password'] ?? '');
        $balance = $parameters['leaveBalance'] ?? User::DEFAULT_BALANCE;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Geçerli bir e-posta adresi girin.'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Şifre en az 6 karakter olmalıdır.'], 400);
        }

        if (!is_int($balance) || $balance < User::MIN_BALANCE || $balance > User::MAX_BALANCE) {
            return $this->json(['error' => 'İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.'], 400);
        }

        if ($this->users->findByEmail($email) !== null) {
            return $this->json(['error' => 'Bu e-posta ile kayıtlı bir kullanıcı zaten var.'], 409);
        }

        $user = User::create($email, $this->hasher->hash($password), $manager->department(), $balance);
        $this->users->save($user);

        return $this->json([
            'message' => 'Kullanıcı oluşturuldu.',
            'user' => UserPresenter::toArray($user),
        ], 201);
    }

    #[Route('/admin/users/{id}', name: 'app_admin_update_user', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->users->get($id);

        if (!$this->currentUser()->isInSameTeamAs($user)) {
            throw new AccessDenied('Bu kullanıcı sizin ekibinizde değil.');
        }

        $parameters = json_decode($request->getContent(), true) ?? [];
        $balance = $parameters['leaveBalance'] ?? null;

        if (!is_int($balance)) {
            return $this->json(['error' => 'İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.'], 400);
        }

        $user->changeLeaveBalance($balance);
        $this->users->save($user);

        return $this->json([
            'message' => 'Kullanıcı güncellendi.',
            'user' => UserPresenter::toArray($user),
        ]);
    }

    private function currentUser(): User
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();

        return $securityUser->user();
    }
}
