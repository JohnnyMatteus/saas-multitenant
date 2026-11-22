<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\LostConnectionDetector;
use Throwable;

/**
 * Separa falha de infraestrutura (banco fora, conexão perdida, deadlock) do resto. A primeira some
 * sozinha e a mensagem não tem culpa: espera-se e tenta-se de novo, sem limite. O resto é tratado
 * como defeito da própria mensagem ou do código e, esgotadas poucas tentativas, vai para a DLQ.
 */
final readonly class FailureClassifier
{
    public function isInfrastructure(Throwable $e): bool
    {
        return (new LostConnectionDetector)->causedByLostConnection($e)
            || (new ConcurrencyErrorDetector)->causedByConcurrencyError($e);
    }
}
