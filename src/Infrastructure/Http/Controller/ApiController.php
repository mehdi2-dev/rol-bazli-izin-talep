<?php

namespace App\Infrastructure\Http\Controller;

use App\Domain\User\User;
use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Giren adaptörlerin ortak yardımcıları: giriş yapan domain kullanıcısı ve JSON gövde.
 * Controller'lar iş kuralı içermez; isteği okur, use case'i çağırır, cevabı biçimler.
 */
abstract class ApiController extends AbstractController
{
    protected function currentUser(): User
    {
        $securityUser = $this->getUser();
        \assert($securityUser instanceof SecurityUser);

        return $securityUser->user();
    }

    /** @return array<string, mixed> */
    protected function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
