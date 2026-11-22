<?php

declare(strict_types=1);

namespace App\Modules\Observability\Tracing;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use Psr\Http\Client\ClientInterface;

/**
 * Monta o TracerProvider a partir de config/tracing.php: SDK com exportador OTLP/HTTP
 * quando há endpoint, no-op quando não há.
 */
final class TracerProviderFactory
{
    /**
     * @param  array{endpoint?: mixed, service_name?: mixed, sample_ratio?: mixed, timeout_seconds?: mixed}  $config
     */
    public static function make(array $config, ?ClientInterface $client = null): TracerProviderInterface
    {
        $endpoint = is_string($config['endpoint'] ?? null) ? rtrim(trim($config['endpoint']), '/') : '';
        if ($endpoint === '') {
            return new NoopTracerProvider;
        }

        $timeout = (float) ($config['timeout_seconds'] ?? 2);
        $factory = new HttpFactory;
        $transport = (new PsrTransportFactory($client ?? new Client(['timeout' => $timeout]), $factory, $factory))->create(
            endpoint: $endpoint.'/v1/traces',
            contentType: ContentTypes::JSON,
            timeout: $timeout,
            maxRetries: 1,
        );

        $resource = ResourceInfo::create(Attributes::create([
            ServiceAttributes::SERVICE_NAME => (string) ($config['service_name'] ?? 'saas-multitenant-api'),
        ]));

        return new TracerProvider(
            new BatchSpanProcessor(new SpanExporter($transport), ClockFactory::getDefault()),
            new ParentBased(new TraceIdRatioBasedSampler((float) ($config['sample_ratio'] ?? 1.0))),
            $resource,
        );
    }
}
