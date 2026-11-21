<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Models\Booking;

final readonly class CancellationResult
{
    public function __construct(
        public Booking $canceled,
        public ?Booking $promoted,
    ) {}
}
