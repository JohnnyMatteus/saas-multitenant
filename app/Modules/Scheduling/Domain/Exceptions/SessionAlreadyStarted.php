<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class SessionAlreadyStarted extends DomainError
{
    public function __construct()
    {
        parent::__construct('A aula já começou.');
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'session_already_started';
    }
}
