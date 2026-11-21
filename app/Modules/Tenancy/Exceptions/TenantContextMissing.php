<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use LogicException;

/**
 * Erro de programação: código tentou acessar dados multitenant sem tenant
 * definido. Não é mapeado para 4xx de propósito; deve virar 500 e alerta.
 */
final class TenantContextMissing extends LogicException
{
    public function __construct()
    {
        parent::__construct('Nenhum tenant definido no contexto; acesso a dados multitenant bloqueado.');
    }
}
