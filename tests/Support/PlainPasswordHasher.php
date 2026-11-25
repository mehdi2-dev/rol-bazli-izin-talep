<?php

namespace App\Tests\Support;

use App\Application\Port\PasswordHasher;

/** Test adaptörü: gerçek şifreleme yapmaz, sadece hangi şifrenin gönderildiğini görünür kılar. */
final class PlainPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:'.$plainPassword;
    }
}
