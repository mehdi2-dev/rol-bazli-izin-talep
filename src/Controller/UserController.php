<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class UserController extends AbstractController
{
    /** Giriş yapan kullanıcının bilgileri (frontend dashboard için). */
    #[Route('/me', name: 'app_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->userToArray($user));
    }

    /** Kullanıcı yönetimi: yöneticinin ekibindeki kullanıcılar. */
    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(UserRepository $userRepository): JsonResponse
    {
        /** @var User $manager */
        $manager = $this->getUser();

        if (!$manager->getDepartment()) {
            return $this->json([]);
        }

        $users = $userRepository->findBy(['department' => $manager->getDepartment()], ['email' => 'ASC']);

        return $this->json(array_map(fn (User $u) => $this->userToArray($u), $users));
    }

    /** Kullanıcı yönetimi: yöneticinin ekibine yeni çalışan ekleme. */
    #[Route('/admin/users', name: 'app_admin_create_user', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        /** @var User $manager */
        $manager = $this->getUser();
        $parameters = json_decode($request->getContent(), true) ?? [];

        $email = strtolower(trim((string) ($parameters['email'] ?? '')));
        $password = (string) ($parameters['password'] ?? '');
        $balance = $parameters['leaveBalance'] ?? 14;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Geçerli bir e-posta adresi girin.'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Şifre en az 6 karakter olmalıdır.'], 400);
        }

        if (!is_int($balance) || $balance < 0 || $balance > 60) {
            return $this->json(['error' => 'İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.'], 400);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Bu e-posta ile kayıtlı bir kullanıcı zaten var.'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setDepartment($manager->getDepartment());
        $user->setLeaveBalance($balance);
        $user->setPassword($hasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Kullanıcı oluşturuldu.',
            'user' => $this->userToArray($user),
        ], 201);
    }

    /** Kullanıcı yönetimi: ekipteki çalışanın izin bakiyesini güncelleme. */
    #[Route('/admin/users/{id}', name: 'app_admin_update_user', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $manager */
        $manager = $this->getUser();
        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Kullanıcı bulunamadı.'], 404);
        }

        if (!$manager->getDepartment() || $user->getDepartment()?->getId() !== $manager->getDepartment()->getId()) {
            return $this->json(['error' => 'Bu kullanıcı sizin ekibinizde değil.'], 403);
        }

        $parameters = json_decode($request->getContent(), true) ?? [];
        $balance = $parameters['leaveBalance'] ?? null;

        if (!is_int($balance) || $balance < 0 || $balance > 60) {
            return $this->json(['error' => 'İzin bakiyesi 0 ile 60 arasında bir tam sayı olmalıdır.'], 400);
        }

        $user->setLeaveBalance($balance);
        $entityManager->flush();

        return $this->json([
            'message' => 'Kullanıcı güncellendi.',
            'user' => $this->userToArray($user),
        ]);
    }

    private function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isManager' => $user->isManager(),
            'department' => $user->getDepartment()?->getName(),
            'leaveBalance' => $user->getLeaveBalance(),
        ];
    }
}
