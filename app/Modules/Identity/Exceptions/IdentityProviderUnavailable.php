<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class IdentityProviderUnavailable extends DomainError
{
    public static function because(string $reason): self
    {
        return new self("Provedor de identidade indisponível: {$reason}");
    }

    public function status(): int
    {
        return 503;
    }

    public function type(): string
    {
        return 'identity_provider_unavailable';
    }
}
