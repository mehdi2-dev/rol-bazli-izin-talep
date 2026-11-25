<?php

namespace App\Application\Port;

/** Çıkan port: şimdiki zaman. Testlerde sabit bir tarih verilebilsin diye soyutlanır. */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
