<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Application\CancelBooking;
use App\Modules\Scheduling\Http\Resources\BookingResource;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class BookingController
{
    public function mine(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->where('member_id', $user->id)
            ->join('class_sessions', 'class_sessions.id', '=', 'bookings.session_id')
            ->orderBy('class_sessions.starts_at')
            ->select('bookings.*')
            ->paginate(50);

        return BookingResource::collection($bookings);
    }

    public function cancel(string $booking, #[CurrentUser] User $user, CancelBooking $cancelBooking): JsonResponse
    {
        $model = Booking::query()->findOrFail($booking);
        Gate::authorize('cancel', $model);

        $result = $cancelBooking->handle($user, $model);

        return new JsonResponse([
            'data' => [
                'canceled' => (new BookingResource($result->canceled))->resolve(),
                'promoted' => $result->promoted ? (new BookingResource($result->promoted))->resolve() : null,
            ],
        ]);
    }
}
