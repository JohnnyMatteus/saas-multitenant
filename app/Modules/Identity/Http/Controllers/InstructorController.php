<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\StudioMemberResource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Instrutores do estúdio, para o admin escolher quem dá uma aula nova.
 * Só lista quem já existe localmente (seed ou provisionamento JIT).
 */
final class InstructorController
{
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        if (! $user->hasRole(Role::StudioAdmin)) {
            throw new AccessDeniedHttpException;
        }

        $instructors = User::query()
            ->where('role', Role::Instructor)
            ->orderBy('name')
            ->get();

        return StudioMemberResource::collection($instructors);
    }
}
