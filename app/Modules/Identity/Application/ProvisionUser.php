<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Jwt\VerifiedClaims;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * Provisionamento just-in-time: cria ou atualiza o espelho local do usuário
 * a partir do token. Deve rodar com o tenant já definido no contexto.
 *
 * O IdP é a fonte da verdade do papel; a cópia local só existe para chaves
 * estrangeiras e consultas (ex.: lista de instrutores).
 */
final class ProvisionUser
{
    public function handle(VerifiedClaims $claims, Role $role): User
    {
        $attributes = ['email' => $claims->email, 'name' => $claims->name, 'role' => $role];

        // createOrFirst tolera dois primeiros requests simultâneos do mesmo usuário
        // apoiando-se na unique (tenant_id, subject).
        $user = User::query()->createOrFirst(['subject' => $claims->subject], $attributes);

        $user->fill($attributes);
        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
