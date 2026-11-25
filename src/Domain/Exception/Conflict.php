<?php

namespace App\Domain\Exception;

/** Var olan bir kayıtla çakışma: aynı tarihlerde talep, aynı e-posta… (HTTP 409) */
final class Conflict extends DomainException
{
}
