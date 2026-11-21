<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Domain\Exceptions\SessionAlreadyStarted;
use App\Modules\Scheduling\Domain\Exceptions\SessionCanceled;
use App\Modules\Scheduling\Events\SessionCanceledByStudio;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;
use Psr\Log\LoggerInterface;

/**
 * Cancelamento de aula pelo estúdio: cancela a aula e todas as reservas
 * (vagas e fila) e avisa cada aluno. Ordem de locks igual à da reserva
 * (aula -> reservas); não há promoção, pois ninguém ocupará a aula.
 */
final readonly class CancelClassSession
{
    private const int DEADLOCK_RETRIES = 3;

    public function __construct(
        private ConnectionInterface $db,
        private Dispatcher $events,
        private LoggerInterface $logger,
    ) {}

    public function handle(ClassSession $loaded, ?string $reason): ClassSession
    {
        $session = $this->db->transaction(fn (): ClassSession => $this->cancel($loaded, $reason), self::DEADLOCK_RETRIES);

        $this->logger->info('class_session_canceled', ['session_id' => $session->id]);

        return $session;
    }

    private function cancel(ClassSession $loaded, ?string $reason): ClassSession
    {
        $now = Date::now();
        $session = ClassSession::query()->lockForUpdate()->findOrFail($loaded->id);

        if ($session->isCanceled()) {
            throw new SessionCanceled;
        }

        if ($session->hasStartedAt($now)) {
            throw new SessionAlreadyStarted;
        }

        $session->fill(['canceled_at' => $now, 'cancel_reason' => $reason])->save();

        $active = Booking::query()
            ->where('session_id', $session->id)
            ->whereIn('status', BookingStatus::activeValues())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($active as $booking) {
            $wasWaitlisted = $booking->status === BookingStatus::Waitlisted;
            $booking->fill(['status' => BookingStatus::Canceled, 'canceled_at' => $now])->save();

            $this->events->dispatch(new SessionCanceledByStudio(
                $session->tenant_id, $session->id, $booking->member_id, $wasWaitlisted,
            ));
        }

        return $session;
    }
}
