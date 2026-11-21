<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Listeners;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\WaitlistPromoted;
use App\Modules\Scheduling\Mail\WaitlistPromotedMail;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Avisa o aluno promovido. Roda na fila (Redis no docker-compose), fora da
 * transação da reserva: falha de SMTP nunca desfaz a promoção.
 */
final class SendWaitlistPromotionEmail implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Mailer $mailer,
    ) {}

    public function handle(WaitlistPromoted $event): void
    {
        $tenant = Tenant::query()->findOrFail($event->tenantId);

        $this->tenantContext->runAs($tenant, function () use ($event, $tenant): void {
            $booking = Booking::query()->with(['session', 'member'])->find($event->bookingId);

            // A reserva pode ter sido cancelada entre a promoção e o envio.
            if ($booking === null || $booking->status !== BookingStatus::Confirmed || $booking->member->email === null) {
                return;
            }

            $this->mailer->to($booking->member->email)->send(new WaitlistPromotedMail(
                studioName: $tenant->name,
                studentName: $booking->member->name,
                classTitle: $booking->session->title,
                startsAtLocal: $booking->session->starts_at->setTimezone($tenant->timezone)->format('d/m/Y \à\s H:i'),
            ));
        });
    }
}
