<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class BookingAlreadyCanceled extends DomainError
{
    public function __construct()
    {
        parent::__construct('Esta reserva já foi cancelada.');
    }

    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'booking_already_canceled';
    }
}
