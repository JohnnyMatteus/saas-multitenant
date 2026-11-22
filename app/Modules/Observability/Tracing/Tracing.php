<?php

declare(strict_types=1);

namespace App\Modules\Observability\Tracing;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

/**
 * Abre um span interno ao redor de um caso de uso. A exceção passa intacta; o span registra só o tipo
 * (ver SpanFailure). Atributos sem dado pessoal: ids de recurso, nunca e-mail ou nome.
 */
final readonly class Tracing
{
    public function __construct(private TracerProviderInterface $provider) {}

    /**
     * @template T
     *
     * @param  non-empty-string  $name
     * @param  callable(): T  $callback
     * @param  array<string, string|int|bool>  $attributes
     * @return T
     */
    public function span(string $name, callable $callback, array $attributes = []): mixed
    {
        $span = $this->provider->getTracer('saas-multitenant')
            ->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($attributes)
            ->startSpan();
        $scope = $span->activate();

        try {
            return $callback();
        } catch (Throwable $e) {
            SpanFailure::record($span, $e);

            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
