<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

use Firebase\JWT\Key;

/**
 * Fonte das chaves públicas que assinam os tokens, indexadas por "kid".
 */
interface KeySetProvider
{
    /**
     * @return array<string, Key>
     */
    public function keys(): array;

    /**
     * Força nova leitura (rotação de chave no IdP). Implementações devem
     * limitar a frequência para que kids inventados não virem DoS no IdP.
     *
     * @return array<string, Key>
     */
    public function refresh(): array;
}
