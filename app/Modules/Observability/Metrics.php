<?php

declare(strict_types=1);

namespace App\Modules\Observability;

use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Psr\Log\LoggerInterface;

/**
 * Fachada fina sobre o cliente Prometheus.
 *
 * Falha de armazenamento de métrica nunca derruba a requisição: vira um
 * aviso no log. Rótulos devem ter cardinalidade baixa (nada de IDs).
 */
final readonly class Metrics
{
    public function __construct(
        private CollectorRegistry $registry,
        private LoggerInterface $logger,
        private string $namespace,
    ) {}

    /**
     * @param  array<string, string>  $labels
     */
    public function increment(string $name, string $help, array $labels = []): void
    {
        try {
            $this->registry
                ->getOrRegisterCounter($this->namespace, $name, $help, array_keys($labels))
                ->inc(array_values($labels));
        } catch (StorageException $e) {
            $this->logger->warning('metrics_storage_failed', ['metric' => $name, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, string>  $labels
     * @param  list<float>|null  $buckets
     */
    public function observe(string $name, string $help, float $value, array $labels = [], ?array $buckets = null): void
    {
        try {
            $this->registry
                ->getOrRegisterHistogram($this->namespace, $name, $help, array_keys($labels), $buckets)
                ->observe($value, array_values($labels));
        } catch (StorageException $e) {
            $this->logger->warning('metrics_storage_failed', ['metric' => $name, 'error' => $e->getMessage()]);
        }
    }

    public function render(): string
    {
        return (new RenderTextFormat)->render($this->registry->getMetricFamilySamples());
    }
}
