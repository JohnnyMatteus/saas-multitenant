<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\WeeklyRecurrence;
use App\Modules\Scheduling\Models\ClassSeries;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;
use Psr\Log\LoggerInterface;

/**
 * Mantém as séries contínuas (rolling) com aulas materializadas até
 * HORIZON_DAYS à frente, no fuso do estúdio. Idempotente: a série é travada,
 * ends_on só avança, e a unique (series_id, starts_at) barra duplicatas.
 * Roda no tenant corrente (o job estabelece o contexto).
 */
final readonly class ExtendSeriesHorizon
{
    public const int HORIZON_DAYS = 90;

    public function __construct(
        private TenantContext $tenantContext,
        private ConnectionInterface $db,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return int Aulas criadas.
     */
    public function handle(): int
    {
        $timezone = $this->tenantContext->current()->timezone;
        $horizon = Date::now()->setTimezone($timezone)->startOfDay()->addDays(self::HORIZON_DAYS);

        $due = ClassSeries::query()
            ->where('rolling', true)
            ->where('ends_on', '<', $horizon->toDateString())
            ->orderBy('id')
            ->pluck('id');

        $created = 0;

        foreach ($due as $seriesId) {
            $created += $this->db->transaction(fn (): int => $this->extend($seriesId, $horizon, $timezone), 3);
        }

        if ($created > 0) {
            $this->logger->info('series_horizon_extended', ['sessions' => $created]);
        }

        return $created;
    }

    private function extend(string $seriesId, CarbonImmutable $horizon, string $timezone): int
    {
        $series = ClassSeries::query()->lockForUpdate()->find($seriesId);

        if ($series === null || ! $series->rolling || $series->ends_on->toDateString() >= $horizon->toDateString()) {
            return 0;
        }

        $created = 0;
        $from = CarbonImmutable::parse($series->ends_on->toDateString(), $timezone)->addDay();

        while ($from->lessThanOrEqualTo($horizon)) {
            $to = $from->addDays(WeeklyRecurrence::MAX_SPAN_DAYS - 1)->min($horizon);

            $recurrence = new WeeklyRecurrence(
                weekdays: $series->weekdays,
                startTime: $series->start_time,
                durationMinutes: $series->duration_minutes,
                startsOn: $from->toDateString(),
                endsOn: $to->toDateString(),
                timezone: $timezone,
            );

            foreach ($recurrence->occurrences() as $occurrence) {
                ClassSession::query()->create([
                    'series_id' => $series->id,
                    'instructor_id' => $series->instructor_id,
                    'title' => $series->title,
                    'starts_at' => $occurrence->startsAt,
                    'ends_at' => $occurrence->endsAt,
                    'capacity' => $series->capacity,
                ]);
                $created++;
            }

            $series->fill(['ends_on' => $to->toDateString()])->save();
            $from = $to->addDay();
        }

        return $created;
    }
}
