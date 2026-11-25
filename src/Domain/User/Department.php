<?php

namespace App\Domain\User;

/** Ekip (departman). Yönetici yalnızca kendi ekibinin taleplerini görür ve sonuçlandırır. */
class Department
{
    private ?int $id = null;

    public function __construct(private string $name)
    {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function equals(?Department $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this === $other || ($this->id !== null && $this->id === $other->id);
    }
}
