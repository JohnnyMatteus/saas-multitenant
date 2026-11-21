<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class CancellationWindowClosed extends DomainError
{
    public function __construct()
    {
        parent::__construct('Prazo de cancelamento encerrado; fale com o estúdio.');
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'cancellation_window_closed';
    }
}
