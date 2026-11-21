<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Policies;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

final class ClassSeriesPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::StudioAdmin);
    }
}
