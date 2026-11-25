<?php

namespace App\Application\Port;

/** Çıkan port: şifreyi güvenli biçimde özetler (Symfony, test için sahte…). */
interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
