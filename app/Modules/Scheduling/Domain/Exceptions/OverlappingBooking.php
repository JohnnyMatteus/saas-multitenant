<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class OverlappingBooking extends DomainError
{
    public function __construct()
    {
        parent::__construct('Você já tem reserva em outra aula no mesmo horário.');
    }

    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'overlapping_booking';
    }
}
