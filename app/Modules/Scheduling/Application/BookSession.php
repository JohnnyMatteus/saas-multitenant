<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Models\User;
use App\Modules\Observability\Metrics;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Domain\Exceptions\AlreadyBooked;
use App\Modules\Scheduling\Domain\Exceptions\OverlappingBooking;
use App\Modules\Scheduling\Domain\Exceptions\SessionAlreadyStarted;
use App\Modules\Scheduling\Domain\SeatAllocation;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Shared\Exceptions\DomainError;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Psr\Log\LoggerInterface;

/**
 * Reserva uma aula para um aluno: vaga se houver, senão lista de espera.
 *
 * Concorrência (ver docs/roteiros/regras-de-agenda.md):
 * 1. trava a linha do aluno: serializa reservas do mesmo aluno e fecha a
 *    corrida de dupla reserva em aulas diferentes no mesmo horário;
 * 2. trava a linha da aula: serializa a disputa pela última vaga;
 * 3. conta as confirmadas com leitura travada (sempre o último commit).
 * A ordem aluno -> aula -> reservas é a mesma em todo o código, sem deadlock.
 */
final readonly class BookSession
{
    private const int DEADLOCK_RETRIES = 3;

    public function __construct(
        private ConnectionInterface $db,
        private Metrics $metrics,
        private LoggerInterface $logger,
    ) {}

    public function handle(User $member, string $sessionId): Booking
    {
        try {
            $booking = $this->db->transaction(
                fn (): Booking => $this->book($member, $sessionId),
                self::DEADLOCK_RETRIES,
            );
        } catch (UniqueConstraintViolationException) {
            // Defesa em profundidade: a unique (session_id, member_id) barrou
            // algo que o lock deveria ter serializado.
            $this->countAttempt('rejected_already_booked');

            throw new AlreadyBooked;
        } catch (DomainError $e) {
            $this->countAttempt('rejected_'.$e->type());

            throw $e;
        }

        $this->countAttempt($booking->status->value);
        $this->logger->info('booking_created', [
            'booking_id' => $booking->id,
            'session_id' => $booking->session_id,
            'status' => $booking->status->value,
        ]);

        return $booking;
    }

    private function book(User $member, string $sessionId): Booking
    {
        $now = Date::now();

        User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
        $session = ClassSession::query()->lockForUpdate()->findOrFail($sessionId);

        if ($session->hasStartedAt($now)) {
            throw new SessionAlreadyStarted;
        }

        $existing = Booking::query()
            ->where('session_id', $session->id)
            ->where('member_id', $member->id)
            ->lockForUpdate()
            ->first();

        if ($existing !== null && $existing->status->isActive()) {
            throw new AlreadyBooked;
        }

        if ($this->hasOverlappingBooking($member, $session)) {
            throw new OverlappingBooking;
        }

        $confirmed = Booking::query()
            ->where('session_id', $session->id)
            ->where('status', BookingStatus::Confirmed)
            ->lockForUpdate()
            ->count();

        $status = SeatAllocation::decide($confirmed, $session->capacity);

        $booking = $existing ?? new Booking(['session_id' => $session->id, 'member_id' => $member->id]);
        $booking->fill([
            'status' => $status,
            'queued_at' => $now,
            'confirmed_at' => $status === BookingStatus::Confirmed ? $now : null,
            'canceled_at' => null,
        ]);
        $booking->save();

        return $booking;
    }

    /**
     * Reservas ativas (vaga ou fila) do aluno em outra aula que cruza o horário.
     * Lista de espera conta: se fosse promovida, viraria dupla reserva.
     */
    private function hasOverlappingBooking(User $member, ClassSession $session): bool
    {
        return Booking::query()
            ->where('member_id', $member->id)
            ->whereIn('status', BookingStatus::activeValues())
            ->where('session_id', '!=', $session->id)
            ->whereHas('session', fn ($query) => $query
                ->where('starts_at', '<', $session->ends_at)
                ->where('ends_at', '>', $session->starts_at))
            ->exists();
    }

    private function countAttempt(string $result): void
    {
        $this->metrics->increment('bookings_total', 'Tentativas de reserva por resultado.', ['result' => $result]);
    }
}
