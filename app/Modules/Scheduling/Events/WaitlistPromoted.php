<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Um aluno saiu da lista de espera e ganhou vaga.
 *
 * Carrega o tenant explicitamente: quem consome o evento numa fila não tem
 * requisição HTTP, portanto não tem tenant no contexto.
 */
final readonly class WaitlistPromoted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $tenantId,
        public string $bookingId,
        public string $sessionId,
        public string $memberId,
    ) {}
}
