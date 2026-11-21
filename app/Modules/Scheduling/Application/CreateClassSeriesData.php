<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

/**
 * Entrada do caso de uso, já validada quanto a formato pela borda HTTP.
 */
final readonly class CreateClassSeriesData
{
    /**
     * @param  list<int>  $weekdays
     */
    public function __construct(
        public string $title,
        public string $instructorId,
        public array $weekdays,
        public string $startTime,
        public int $durationMinutes,
        public int $capacity,
        public string $startsOn,
        public string $endsOn,
    ) {}
}
