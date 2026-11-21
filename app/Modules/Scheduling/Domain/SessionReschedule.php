<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use Carbon\CarbonImmutable;

/**
 * Move uma ocorrência para outro horário local mantendo a data local, com a
 * conversão para UTC feita para aquela data (o deslocamento pode ter mudado).
 */
final class SessionReschedule
{
    public static function apply(CarbonImmutable $startsAt, string $timezone, string $startTime, int $durationMinutes): Occurrence
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $startTime));
        $newStart = $startsAt->setTimezone($timezone)->setTime($hour, $minute)->utc();

        return new Occurrence($newStart, $newStart->addMinutes($durationMinutes));
    }
}
