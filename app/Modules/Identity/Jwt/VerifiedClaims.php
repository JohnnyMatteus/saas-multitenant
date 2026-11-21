<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jwt;

/**
 * Subconjunto do payload do JWT que a aplicação usa, já validado.
 */
final readonly class VerifiedClaims
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $subject,
        public string $tenant,
        public array $roles,
        public ?string $email,
        public string $name,
    ) {}
}
