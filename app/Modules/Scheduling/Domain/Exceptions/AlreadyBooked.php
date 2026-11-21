<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class AlreadyBooked extends DomainError
{
    public function __construct()
    {
        parent::__construct('Você já tem reserva nesta aula.');
    }

    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'already_booked';
    }
}
