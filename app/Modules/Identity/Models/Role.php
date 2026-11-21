<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

/**
 * Papéis do estúdio, vindos das realm roles do Keycloak.
 */
enum Role: string
{
    case StudioAdmin = 'studio_admin';
    case Instructor = 'instructor';
    case Student = 'student';

    /**
     * Escolhe o papel de maior privilégio entre as roles do token.
     * Roles desconhecidas (ex.: "offline_access") são ignoradas.
     *
     * @param  list<string>  $claimRoles
     */
    public static function highestFrom(array $claimRoles): ?self
    {
        foreach ([self::StudioAdmin, self::Instructor, self::Student] as $role) {
            if (in_array($role->value, $claimRoles, true)) {
                return $role;
            }
        }

        return null;
    }
}
