<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

use Firebase\JWT\Key;

/**
 * Modo de teste/local: uma única chave pública RSA em PEM.
 */
final readonly class StaticKeySetProvider implements KeySetProvider
{
    public function __construct(
        private string $kid,
        private string $publicKeyPem,
    ) {}

    public function keys(): array
    {
        return [$this->kid => new Key($this->publicKeyPem, 'RS256')];
    }

    public function refresh(): array
    {
        return $this->keys();
    }
}
