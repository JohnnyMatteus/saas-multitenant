<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Policies;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\ClassSession;

final class ClassSessionPolicy
{
    /**
     * Lista de presença: admin do estúdio ou o instrutor da própria aula.
     */
    public function viewRoster(User $user, ClassSession $session): bool
    {
        if ($user->tenant_id !== $session->tenant_id) {
            return false;
        }

        return $user->hasRole(Role::StudioAdmin)
            || ($user->hasRole(Role::Instructor) && $session->instructor_id === $user->id);
    }

    /**
     * Editar ou cancelar aula: só o admin do estúdio.
     */
    public function manage(User $user, ClassSession $session): bool
    {
        return $user->tenant_id === $session->tenant_id && $user->hasRole(Role::StudioAdmin);
    }
}
