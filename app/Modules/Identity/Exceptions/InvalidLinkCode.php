<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class InvalidLinkCode extends DomainError
{
    public function __construct()
    {
        parent::__construct('Código de vínculo inválido, expirado ou já usado.');
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'invalid_link_code';
    }
}
