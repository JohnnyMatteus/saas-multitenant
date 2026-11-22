<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\WeeklyRecurrence;
use App\Modules\Scheduling\Models\ClassSeries;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Cria uma aula recorrente e materializa suas ocorrências no fuso do estúdio.
 */
final readonly class CreateClassSeries
{
    public function __construct(
        private TenantContext $tenantContext,
        private ConnectionInterface $db,
        private LoggerInterface $logger,
    ) {}

    public function handle(CreateClassSeriesData $data): ClassSeries
    {
        $tenant = $this->tenantContext->current();

        $recurrence = new WeeklyRecurrence(
            weekdays: $data->weekdays,
            startTime: $data->startTime,
            durationMinutes: $data->durationMinutes,
            startsOn: $data->startsOn,
            endsOn: $data->endsOn,
            timezone: $tenant->timezone,
        );

        // A consulta já é filtrada pelo tenant: instrutor de outro estúdio
        // simplesmente "não existe" aqui.
        $instructorExists = User::query()
            ->whereKey($data->instructorId)
            ->where('role', Role::Instructor)
            ->exists();

        if (! $instructorExists) {
            throw ValidationException::withMessages(['instructor_id' => 'Instrutor não encontrado neste estúdio.']);
        }

        $series = $this->db->transaction(function () use ($data, $recurrence): ClassSeries {
            $series = ClassSeries::query()->create([
                'instructor_id' => $data->instructorId,
                'title' => $data->title,
                'weekdays' => $recurrence->weekdays,
                'start_time' => $recurrence->startTime,
                'duration_minutes' => $recurrence->durationMinutes,
                'capacity' => $data->capacity,
                'starts_on' => $recurrence->startsOn,
                'ends_on' => $recurrence->endsOn,
                'rolling' => $data->rolling,
            ]);

            foreach ($recurrence->occurrences() as $occurrence) {
                ClassSession::query()->create([
                    'series_id' => $series->id,
                    'instructor_id' => $data->instructorId,
                    'title' => $data->title,
                    'starts_at' => $occurrence->startsAt,
                    'ends_at' => $occurrence->endsAt,
                    'capacity' => $data->capacity,
                ]);
            }

            return $series;
        });

        $series->loadCount('sessions');

        $this->logger->info('class_series_created', [
            'series_id' => $series->id,
            'sessions' => $series->getAttribute('sessions_count'),
        ]);

        return $series;
    }
}
