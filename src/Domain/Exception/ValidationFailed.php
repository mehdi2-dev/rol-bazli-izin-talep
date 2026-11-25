<?php

namespace App\Domain\Exception;

/** Kural ihlali: geçersiz tarih, yetersiz bakiye, eksik açıklama… (HTTP 400) */
final class ValidationFailed extends DomainException
{
}
