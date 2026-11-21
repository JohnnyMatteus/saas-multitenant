<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Antecedência mínima, definida por estúdio, para o aluno cancelar uma vaga
 * confirmada. Dentro da janela, só o admin do estúdio cancela.
 */
final readonly class CancellationWindow
{
    public function __construct(public int $minutes)
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('A janela de cancelamento não pode ser negativa.');
        }
    }

    public function deadlineFor(CarbonImmutable $sessionStartsAt): CarbonImmutable
    {
        return $sessionStartsAt->subMinutes($this->minutes);
    }

    /**
     * Aberta enquanto "agora" for estritamente anterior ao prazo.
     */
    public function isOpen(CarbonImmutable $sessionStartsAt, CarbonImmutable $now): bool
    {
        return $now->lessThan($this->deadlineFor($sessionStartsAt));
    }
}
