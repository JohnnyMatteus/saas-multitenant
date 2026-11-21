<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Listeners;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\BookingConfirmed;
use App\Modules\Scheduling\Mail\BookingConfirmedMail;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Tenancy\Models\Tenant;

final class SendBookingConfirmationEmail extends SendsBookingMail
{
    public function handle(BookingConfirmed $event): void
    {
        $this->deliver($event->tenantId, function (Tenant $tenant) use ($event): ?array {
            $booking = Booking::query()->with(['session', 'member'])->find($event->bookingId);

            if ($booking === null || $booking->status !== BookingStatus::Confirmed) {
                return null;
            }

            return [$booking->member, new BookingConfirmedMail(
                $tenant->name, $booking->member->name, $booking->session->title, $this->localStart($booking->session, $tenant),
            )];
        });
    }
}
