<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Policies;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\Booking;

/**
 * As comparações de tenant_id são defesa em profundidade: o global scope já
 * impede que um registro de outro tenant chegue até aqui.
 */
final class BookingPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Student);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        return $user->hasRole(Role::StudioAdmin) || $booking->member_id === $user->id;
    }
}
