<?php

declare(strict_types=1);

namespace App\Modules\Observability;

use App\Modules\Observability\Tracing\ExportBackoff;
use App\Modules\Observability\Tracing\TracerProviderFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as SdkTracerProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Psr\Log\LoggerInterface;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorRegistry::class, function (Application $app): CollectorRegistry {
            $storage = match ($app->make(Config::class)->get('metrics.storage')) {
                'apcu' => new APCng,
                'in_memory' => new InMemory,
                default => throw new InvalidArgumentException('METRICS_STORAGE deve ser "apcu" ou "in_memory".'),
            };

            return new CollectorRegistry($storage, registerDefaultMetrics: false);
        });

        $this->app->singleton(Metrics::class, fn (Application $app): Metrics => new Metrics(
            registry: $app->make(CollectorRegistry::class),
            logger: $app->make(LoggerInterface::class),
            namespace: (string) $app->make(Config::class)->get('metrics.namespace'),
        ));

        $this->app->bind(ExportBackoff::class, fn (Application $app): ExportBackoff => new ExportBackoff(
            cache: $app->make(CacheRepository::class),
            logger: $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(TracerProviderInterface::class, function (Application $app): TracerProviderInterface {
            /** @var array{endpoint?: mixed, service_name?: mixed, sample_ratio?: mixed, timeout_seconds?: mixed} $config */
            $config = $app->make(Config::class)->get('tracing');

            if (! is_string($config['endpoint'] ?? null) || trim($config['endpoint']) === '') {
                return TracerProviderFactory::make($config);
            }

            $backoff = $app->make(ExportBackoff::class);

            // Coletor fora do ar: sem tracer real enquanto a janela de backoff estiver aberta.
            return $backoff->isOpen() ? new NoopTracerProvider : TracerProviderFactory::make($config, backoff: $backoff);
        });
    }

    public function boot(): void
    {
        // Depois da resposta enviada: o lote de spans sai sem somar latência ao cliente.
        $this->app->terminating(function (): void {
            $provider = $this->app->make(TracerProviderInterface::class);
            if ($provider instanceof SdkTracerProvider) {
                $this->app->make(ExportBackoff::class)->guard(fn (): bool => $provider->forceFlush());
            }
        });
    }
}
