<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\BookingReminderDue;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;

/**
 * Marca e despacha o lembrete das reservas confirmadas cuja aula começa nas
 * próximas LEAD_HOURS horas. O UPDATE condicional em reminder_sent_at é o
 * que garante um lembrete por reserva mesmo com duas execuções simultâneas.
 * Roda no tenant corrente.
 */
final readonly class SendBookingReminders
{
    public const int LEAD_HOURS = 24;

    public function __construct(private Dispatcher $events) {}

    /**
     * @return int Lembretes despachados.
     */
    public function handle(): int
    {
        $now = Date::now();
        $dispatched = 0;

        $due = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereHas('session', fn ($query) => $query
                ->whereNull('canceled_at')
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $now->addHours(self::LEAD_HOURS)))
            ->get(['id', 'tenant_id']);

        foreach ($due as $booking) {
            $claimed = Booking::query()
                ->whereKey($booking->id)
                ->whereNull('reminder_sent_at')
                ->where('status', BookingStatus::Confirmed)
                ->update(['reminder_sent_at' => $now]);

            if ($claimed === 1) {
                $this->events->dispatch(new BookingReminderDue($booking->tenant_id, $booking->id));
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
