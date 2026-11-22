<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Reserva confirmada na hora da reserva (a promoção da fila tem evento próprio).
 */
final readonly class BookingConfirmed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $bookingId,
    ) {}
}
