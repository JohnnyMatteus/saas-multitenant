<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

/**
 * Token válido, mas o tenant da claim não existe ou está inativo.
 */
final class TenantNotRecognized extends DomainError
{
    public function __construct()
    {
        parent::__construct('Estúdio não reconhecido ou inativo.');
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return 'tenant_not_recognized';
    }
}
