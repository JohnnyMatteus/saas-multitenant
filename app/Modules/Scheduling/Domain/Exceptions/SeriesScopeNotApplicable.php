<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class SeriesScopeNotApplicable extends DomainError
{
    public function __construct()
    {
        parent::__construct('Aula avulsa não pertence a uma série: edite apenas esta ocorrência.');
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'series_scope_not_applicable';
    }
}
