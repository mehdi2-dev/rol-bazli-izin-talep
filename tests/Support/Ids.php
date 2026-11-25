<?php

namespace App\Tests\Support;

/** Veritabanının yaptığı gibi nesneye id verir (yalnızca testlerde). */
final class Ids
{
    public static function assign(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
