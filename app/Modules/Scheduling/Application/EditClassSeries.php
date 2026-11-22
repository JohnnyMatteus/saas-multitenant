<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Domain\Exceptions\SeriesScopeNotApplicable;
use App\Modules\Scheduling\Domain\Exceptions\SessionAlreadyStarted;
use App\Modules\Scheduling\Domain\Exceptions\SessionCanceled;
use App\Modules\Scheduling\Domain\SeriesEditScope;
use App\Modules\Scheduling\Domain\SessionReschedule;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSeries;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Edita uma ocorrência, esta e as próximas, ou a série inteira, sem desfazer
 * o que os alunos já reservaram:
 * - capacidade nunca cai abaixo das vagas confirmadas (a aula é preservada);
 * - horário só muda em aulas sem reservas ativas;
 * - título e instrutor sempre mudam; capacidade maior promove a fila.
 * "Esta e as próximas" divide a série em duas: as aulas passadas continuam
 * na série original, com ends_on encerrado no dia anterior.
 *
 * Ordem de locks: série -> aulas (por id) -> reservas. A reserva nunca trava
 * a série, então não há ciclo com BookSession (aluno -> aula -> reservas).
 */
final readonly class EditClassSeries
{
    private const int DEADLOCK_RETRIES = 3;

    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
        private WaitlistPromoter $promoter,
        private LoggerInterface $logger,
    ) {}

    public function handle(ClassSession $target, SeriesEditData $data, SeriesEditScope $scope): SeriesEditResult
    {
        if ($target->series_id === null && $scope !== SeriesEditScope::Occurrence) {
            throw new SeriesScopeNotApplicable;
        }

        if ($data->instructorId !== null && ! User::query()->whereKey($data->instructorId)->where('role', Role::Instructor)->exists()) {
            throw ValidationException::withMessages(['instructor_id' => 'Instrutor não encontrado neste estúdio.']);
        }

        $result = $this->db->transaction(fn (): SeriesEditResult => $this->edit($target, $data, $scope), self::DEADLOCK_RETRIES);

        $this->logger->info('class_series_edited', [
            'series_id' => $result->seriesId,
            'scope' => $scope->value,
            'updated' => count($result->updated),
            'skipped' => count($result->skipped),
        ]);

        return $result;
    }

    private function edit(ClassSession $loaded, SeriesEditData $data, SeriesEditScope $scope): SeriesEditResult
    {
        $now = Date::now();
        $timezone = $this->tenantContext->current()->timezone;

        $series = $loaded->series_id === null ? null : ClassSeries::query()->lockForUpdate()->findOrFail($loaded->series_id);
        $target = ClassSession::query()->lockForUpdate()->findOrFail($loaded->id);

        if ($target->isCanceled()) {
            throw new SessionCanceled;
        }

        if ($target->hasStartedAt($now)) {
            throw new SessionAlreadyStarted;
        }

        $isFirstOfSeries = $series !== null
            && ! ClassSession::query()->where('series_id', $series->id)->where('starts_at', '<', $target->starts_at)->exists();

        $sessions = $this->affectedSessions($target, $series, $scope, $now);
        $bookingsBySession = $this->lockActiveBookings($sessions);

        $updated = [];
        $skipped = [];

        foreach ($sessions->reject(fn (ClassSession $s): bool => $s->isCanceled()) as $session) {
            $reasons = $this->applyToSession($session, $data, $bookingsBySession[$session->id] ?? [], $timezone, $now);

            if ($reasons === []) {
                $updated[] = $session->id;
            } else {
                $skipped[] = ['session_id' => $session->id, 'reasons' => $reasons];
            }
        }

        $seriesId = $series?->id;

        if ($series !== null && $scope === SeriesEditScope::Following && ! $isFirstOfSeries) {
            $seriesId = $this->splitSeries($series, $target, $sessions, $data, $timezone);
        } elseif ($series !== null && $scope !== SeriesEditScope::Occurrence) {
            $series->fill($this->seriesAttributes($data))->save();
        }

        return new SeriesEditResult($seriesId, $updated, $skipped);
    }

    /**
     * @return Collection<int, ClassSession>
     */
    private function affectedSessions(ClassSession $target, ?ClassSeries $series, SeriesEditScope $scope, CarbonImmutable $now): Collection
    {
        if ($scope === SeriesEditScope::Occurrence || $series === null) {
            return collect([$target]);
        }

        $query = ClassSession::query()->where('series_id', $series->id);

        if ($scope === SeriesEditScope::Following) {
            $query->where('starts_at', '>=', $target->starts_at);
        } else {
            $query->where('starts_at', '>', $now);
        }

        return $query->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, array<int, Booking>>
     */
    private function lockActiveBookings(Collection $sessions): array
    {
        return Booking::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->whereIn('status', BookingStatus::activeValues())
            ->orderBy('session_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('session_id')
            ->map(fn (Collection $group): array => $group->values()->all())
            ->all();
    }

    /**
     * @param  array<int, Booking>  $bookings
     * @return list<string> Motivos pelos quais parte da edição foi preservada.
     */
    private function applyToSession(ClassSession $session, SeriesEditData $data, array $bookings, string $timezone, CarbonImmutable $now): array
    {
        $reasons = [];
        $attributes = array_filter([
            'title' => $data->title,
            'instructor_id' => $data->instructorId,
        ], fn (?string $value): bool => $value !== null);

        $confirmed = count(array_filter($bookings, fn (Booking $b): bool => $b->status === BookingStatus::Confirmed));
        $raisesCapacity = false;

        if ($data->capacity !== null) {
            if ($data->capacity >= $confirmed) {
                $raisesCapacity = $data->capacity > $session->capacity;
                $attributes['capacity'] = $data->capacity;
            } else {
                $reasons[] = 'capacity_below_confirmed';
            }
        }

        if ($data->changesTime()) {
            if ($bookings === []) {
                $occurrence = SessionReschedule::apply(
                    $session->starts_at,
                    $timezone,
                    $data->startTime ?? $session->starts_at->setTimezone($timezone)->format('H:i'),
                    $data->durationMinutes ?? (int) $session->starts_at->diffInMinutes($session->ends_at),
                );
                $attributes['starts_at'] = $occurrence->startsAt;
                $attributes['ends_at'] = $occurrence->endsAt;
            } else {
                $reasons[] = 'has_bookings';
            }
        }

        $session->fill($attributes)->save();

        if ($raisesCapacity) {
            $this->promoter->fillFreeSeats($session, $now);
        }

        return $reasons;
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     */
    private function splitSeries(ClassSeries $series, ClassSession $target, Collection $sessions, SeriesEditData $data, string $timezone): string
    {
        $firstDay = $target->starts_at->setTimezone($timezone)->startOfDay();

        $following = ClassSeries::query()->create([
            'instructor_id' => $series->instructor_id,
            'title' => $series->title,
            'weekdays' => $series->weekdays,
            'start_time' => $series->start_time,
            'duration_minutes' => $series->duration_minutes,
            'capacity' => $series->capacity,
            'starts_on' => $firstDay->toDateString(),
            'ends_on' => $series->ends_on,
            'rolling' => $series->rolling,
            ...$this->seriesAttributes($data),
        ]);

        $series->fill(['ends_on' => $firstDay->subDay()->toDateString(), 'rolling' => false])->save();

        ClassSession::query()->whereIn('id', $sessions->pluck('id'))->update(['series_id' => $following->id]);

        return $following->id;
    }

    /**
     * @return array<string, int|string>
     */
    private function seriesAttributes(SeriesEditData $data): array
    {
        return array_filter([
            'title' => $data->title,
            'instructor_id' => $data->instructorId,
            'capacity' => $data->capacity,
            'start_time' => $data->startTime,
            'duration_minutes' => $data->durationMinutes,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
