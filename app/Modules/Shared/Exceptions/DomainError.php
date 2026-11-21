<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use RuntimeException;

/**
 * Erro de regra de negócio, sempre esperado e tratável pelo cliente da API.
 *
 * Cada subclasse define o status HTTP e um código estável ("type") que o
 * front-end pode usar sem depender do texto da mensagem.
 */
abstract class DomainError extends RuntimeException
{
    abstract public function status(): int;

    abstract public function type(): string;
}
