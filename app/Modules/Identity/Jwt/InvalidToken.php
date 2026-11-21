<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

use RuntimeException;

/**
 * Token rejeitado. O motivo vai para o log, nunca para o cliente: a resposta
 * é sempre um 401 genérico para não ajudar quem está forjando tokens.
 */
final class InvalidToken extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
