<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use LogicException;

/**
 * Erro de programação: tentativa de gravar um registro com tenant_id diferente
 * do tenant corrente, ou de mover um registro para outro tenant.
 */
final class CrossTenantWrite extends LogicException
{
    public static function onCreate(string $model): self
    {
        return new self("Tentativa de criar {$model} em outro tenant.");
    }

    public static function onUpdate(string $model): self
    {
        return new self("Tentativa de alterar o tenant_id de {$model}.");
    }
}
