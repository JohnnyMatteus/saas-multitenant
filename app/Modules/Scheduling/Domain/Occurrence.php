<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use Carbon\CarbonImmutable;

/**
 * Uma ocorrência de aula, sempre em UTC.
 */
final readonly class Occurrence
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
