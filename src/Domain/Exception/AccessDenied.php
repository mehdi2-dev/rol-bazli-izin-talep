<?php

namespace App\Domain\Exception;

/** İşlem bu kullanıcıya kapalı: başka ekibin talebi, kendi talebini onaylama… (HTTP 403) */
final class AccessDenied extends DomainException
{
}
