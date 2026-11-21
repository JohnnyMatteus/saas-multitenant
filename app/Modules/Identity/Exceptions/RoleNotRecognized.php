<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

/**
 * Token válido, mas sem nenhum papel do estúdio (admin, instrutor ou aluno).
 */
final class RoleNotRecognized extends DomainError
{
    public function __construct()
    {
        parent::__construct('Usuário sem papel reconhecido no estúdio.');
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return 'role_not_recognized';
    }
}
