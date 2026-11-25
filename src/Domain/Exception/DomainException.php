<?php

namespace App\Domain\Exception;

/**
 * İş kuralı ihlallerinin ortak atası.
 *
 * Domain HTTP bilmez: hangi alt sınıfın hangi durum koduna dönüştüğüne
 * Infrastructure katmanı (ApiSubscriber) karar verir.
 */
abstract class DomainException extends \DomainException
{
}
