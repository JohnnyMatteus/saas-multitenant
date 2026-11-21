<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Application\BookSession;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Http\Resources\BookingResource;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class SessionBookingController
{
    public function index(string $session): AnonymousResourceCollection
    {
        $classSession = ClassSession::query()->findOrFail($session);
        Gate::authorize('viewRoster', $classSession);

        $bookings = $classSession->bookings()
            ->with('member')
            ->whereIn('status', BookingStatus::activeValues())
            ->orderBy('status') // "confirmed" antes de "waitlisted"
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();

        return BookingResource::collection($bookings);
    }

    public function store(string $session, #[CurrentUser] User $user, BookSession $bookSession): JsonResponse
    {
        Gate::authorize('create', Booking::class);

        $booking = $bookSession->handle($user, $session);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }
}
