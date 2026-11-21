<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Events;

final readonly class BookingReminderDue
{
    public function __construct(
        public string $tenantId,
        public string $bookingId,
    ) {}
}
