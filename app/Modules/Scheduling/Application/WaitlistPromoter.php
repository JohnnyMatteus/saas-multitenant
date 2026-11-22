<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Observability\Metrics;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\WaitlistPromoted;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Preenche vagas livres com a fila, na ordem de chegada. Chamado dentro da
 * transação de quem liberou a vaga, com a linha da aula já travada.
 */
final readonly class WaitlistPromoter
{
    public function __construct(
        private Dispatcher $events,
        private Metrics $metrics,
    ) {}

    /**
     * @return list<Booking>
     */
    public function fillFreeSeats(ClassSession $session, CarbonImmutable $now): array
    {
        $confirmed = Booking::query()
            ->where('session_id', $session->id)
            ->where('status', BookingStatus::Confirmed)
            ->lockForUpdate()
            ->count();

        $free = $session->capacity - $confirmed;

        if ($free <= 0) {
            return [];
        }

        $queue = Booking::query()
            ->where('session_id', $session->id)
            ->where('status', BookingStatus::Waitlisted)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit($free)
            ->lockForUpdate()
            ->get();

        $promoted = [];

        foreach ($queue as $next) {
            $next->fill(['status' => BookingStatus::Confirmed, 'confirmed_at' => $now])->save();

            $this->metrics->increment('waitlist_promotions_total', 'Promoções automáticas da lista de espera.');
            // Despachado só após o commit: ninguém é notificado de uma promoção desfeita.
            $this->events->dispatch(new WaitlistPromoted($session->tenant_id, $next->id, $session->id, $next->member_id));

            $promoted[] = $next;
        }

        return $promoted;
    }
}
