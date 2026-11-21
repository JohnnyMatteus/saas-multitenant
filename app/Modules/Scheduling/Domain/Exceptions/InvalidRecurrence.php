<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class InvalidRecurrence extends DomainError
{
    public static function because(string $reason): self
    {
        return new self("Recorrência inválida: {$reason}.");
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'invalid_recurrence';
    }
}
