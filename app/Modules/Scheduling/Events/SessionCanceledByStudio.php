<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Um evento por aluno afetado: a reserva já está cancelada quando o e-mail
 * sai, então o evento carrega se o aluno tinha vaga ou estava na fila.
 */
final readonly class SessionCanceledByStudio implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $sessionId,
        public string $memberId,
        public bool $wasWaitlisted,
    ) {}
}
