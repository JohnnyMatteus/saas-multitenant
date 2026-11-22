<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Domain\CancellationWindow;
use App\Modules\Scheduling\Domain\Exceptions\BookingAlreadyCanceled;
use App\Modules\Scheduling\Domain\Exceptions\CancellationWindowClosed;
use App\Modules\Scheduling\Domain\Exceptions\SessionAlreadyStarted;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;
use Psr\Log\LoggerInterface;

/**
 * Cancela uma reserva e, se ela liberou uma vaga, promove o primeiro da fila
 * na mesma transação: não existe instante em que a vaga fique ociosa com
 * gente esperando.
 *
 * A autorização (dono da reserva ou admin) é da policy; aqui ficam as regras
 * de tempo, que dependem do papel: o admin cancela dentro da janela.
 */
final readonly class CancelBooking
{
    private const int DEADLOCK_RETRIES = 3;

    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
        private WaitlistPromoter $promoter,
        private LoggerInterface $logger,
    ) {}

    public function handle(User $actor, Booking $booking): CancellationResult
    {
        $result = $this->db->transaction(
            fn (): CancellationResult => $this->cancel($actor, $booking),
            self::DEADLOCK_RETRIES,
        );

        $this->logger->info('booking_canceled', [
            'booking_id' => $result->canceled->id,
            'by_role' => $actor->role->value,
            'promoted_booking_id' => $result->promoted?->id,
        ]);

        return $result;
    }

    private function cancel(User $actor, Booking $loaded): CancellationResult
    {
        $now = Date::now();

        // Mesma ordem de locks da reserva (aula antes das reservas). A linha é
        // relida sob lock: o estado carregado antes da transação pode estar velho.
        $session = ClassSession::query()->lockForUpdate()->findOrFail($loaded->session_id);
        $booking = Booking::query()->lockForUpdate()->findOrFail($loaded->id);

        if ($booking->status === BookingStatus::Canceled) {
            throw new BookingAlreadyCanceled;
        }

        if ($session->hasStartedAt($now)) {
            throw new SessionAlreadyStarted;
        }

        $window = new CancellationWindow($this->tenantContext->current()->cancellation_window_minutes);
        $freesSeat = $booking->status === BookingStatus::Confirmed;

        if ($freesSeat && ! $actor->hasRole(Role::StudioAdmin) && ! $window->isOpen($session->starts_at, $now)) {
            throw new CancellationWindowClosed;
        }

        $booking->fill(['status' => BookingStatus::Canceled, 'canceled_at' => $now])->save();

        $promoted = $freesSeat ? ($this->promoter->fillFreeSeats($session, $now)[0] ?? null) : null;

        return new CancellationResult($booking, $promoted);
    }
}
