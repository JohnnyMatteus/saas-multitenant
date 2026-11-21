<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Booking $resource
 */
final class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource;

        return [
            'id' => $booking->id,
            'session_id' => $booking->session_id,
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $booking->member->id,
                'name' => $booking->member->name,
            ]),
            'session' => $this->whenLoaded('session', fn (): array => (new SessionResource($booking->session))->resolve($request)),
            'status' => $booking->status->value,
            'waitlist_position' => $booking->waitlistPosition(),
            'queued_at' => $booking->queued_at->toIso8601ZuluString('microsecond'),
            'confirmed_at' => $booking->confirmed_at?->toIso8601ZuluString(),
            'canceled_at' => $booking->canceled_at?->toIso8601ZuluString(),
        ];
    }
}
