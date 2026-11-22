<?php

declare(strict_types=1);

namespace App\Modules\Observability\Tracing;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Coletor fora do ar não pode custar um timeout e uma linha de log por requisição.
 * A primeira falha abre uma janela (compartilhada entre processos pelo cache) em que a
 * exportação é pulada, e o aviso sai uma vez por janela.
 */
final readonly class ExportBackoff
{
    private const string KEY = 'otel:export-backoff';

    public function __construct(
        private Repository $cache,
        private LoggerInterface $logger,
        private int $windowSeconds = 60,
    ) {}

    public function isOpen(): bool
    {
        try {
            return $this->cache->has(self::KEY);
        } catch (Throwable) {
            return false;
        }
    }

    public function recordFailure(): void
    {
        try {
            if ($this->cache->add(self::KEY, 1, $this->windowSeconds)) {
                $this->logger->warning('otel_export_failed', ['retry_after_seconds' => $this->windowSeconds]);
            }
        } catch (Throwable) {
            // Sem cache não há como espaçar o aviso; melhor calar do que repetir a cada requisição.
        }
    }

    /**
     * Roda o flush sem deixar exceção sair: telemetria nunca derruba a resposta.
     *
     * @param  callable(): mixed  $flush
     */
    public function guard(callable $flush): void
    {
        try {
            $flush();
        } catch (Throwable) {
            $this->recordFailure();
        }
    }
}
