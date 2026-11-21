<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Listeners;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\BookingReminderDue;
use App\Modules\Scheduling\Mail\BookingReminderMail;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Tenancy\Models\Tenant;

final class SendBookingReminderEmail extends SendsBookingMail
{
    public function handle(BookingReminderDue $event): void
    {
        $this->deliver($event->tenantId, function (Tenant $tenant) use ($event): ?array {
            $booking = Booking::query()->with(['session', 'member'])->find($event->bookingId);

            // Cancelada ou aula cancelada entre o despacho e o envio.
            if ($booking === null || $booking->status !== BookingStatus::Confirmed || $booking->session->isCanceled()) {
                return null;
            }

            return [$booking->member, new BookingReminderMail(
                $tenant->name, $booking->member->name, $booking->session->title, $this->localStart($booking->session, $tenant),
            )];
        });
    }
}
