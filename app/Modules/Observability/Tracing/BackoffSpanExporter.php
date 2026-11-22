<?php

declare(strict_types=1);

namespace App\Modules\Observability\Tracing;

use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * Decora o exportador: pula a exportação durante a janela de backoff e a abre quando o
 * coletor não aceita um lote (o exportador OTLP devolve `false` na falha, sem lançar).
 */
final readonly class BackoffSpanExporter implements SpanExporterInterface
{
    public function __construct(private SpanExporterInterface $inner, private ExportBackoff $backoff) {}

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        if ($this->backoff->isOpen()) {
            return new CompletedFuture(true);
        }

        return $this->inner->export($batch, $cancellation)->map(function (mixed $ok): mixed {
            if ($ok === false) {
                $this->backoff->recordFailure();
            }

            return $ok;
        });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->inner->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->inner->forceFlush($cancellation);
    }
}
