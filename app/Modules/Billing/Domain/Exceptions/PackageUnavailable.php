<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class PackageUnavailable extends DomainError
{
    public function __construct()
    {
        parent::__construct('Este pacote não está à venda.');
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'package_unavailable';
    }
}
