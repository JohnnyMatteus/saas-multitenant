<?php

declare(strict_types=1);

namespace App\Modules\Observability\Http\Middleware;

use App\Modules\Observability\Metrics;
use App\Modules\Observability\Tracing\SpanFailure;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Primeiro middleware da pilha:
 * - correlação: X-Request-Id e trace_id (W3C traceparent) no contexto de log;
 * - log de acesso estruturado, uma linha por requisição;
 * - métricas RED (taxa, erros, duração) por rota.
 */
final readonly class ObserveHttpRequests
{
    private const string TRACEPARENT = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    private const array DURATION_BUCKETS = [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0];

    public function __construct(
        private Metrics $metrics,
        private TracerProviderInterface $tracers,
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        Context::flush();
        $requestId = $this->requestId($request);

        // O trace do chamador (gateway, front-end) vira o pai do span desta requisição.
        $parent = TraceContextPropagator::getInstance()->extract($this->traceHeaders($request));
        $span = $this->tracers->getTracer('saas-multitenant')
            ->spanBuilder('HTTP '.$request->method())
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        // Com tracer real, o trace_id dos logs é o do span; no-op, o do traceparent ou um novo.
        $traceId = $span->getContext()->isValid() ? $span->getContext()->getTraceId() : $this->traceId($request);
        Context::add(['request_id' => $requestId, 'trace_id' => $traceId]);
        $scope = $span->storeInContext($parent)->activate();

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $e) {
            SpanFailure::record($span, $e);
            $span->end();

            throw $e;
        } finally {
            $scope->detach();
        }

        $seconds = (hrtime(true) - $startedAt) / 1e9;
        $route = $request->route()?->uri() ?? 'unmatched';
        $labels = ['method' => $request->method(), 'route' => $route, 'status' => (string) $response->getStatusCode()];

        $status = $response->getStatusCode();
        $span->updateName($request->method().' /'.ltrim($route, '/'));
        $span->setAttribute(HttpAttributes::HTTP_REQUEST_METHOD, $request->method());
        $span->setAttribute(HttpAttributes::HTTP_ROUTE, $route);
        $span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $status);
        $tenantId = Context::get('tenant_id');
        if (is_string($tenantId)) {
            $span->setAttribute('tenant.id', $tenantId);
        }
        if ($status >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
        $span->end();

        $this->metrics->increment('http_requests_total', 'Requisições HTTP por rota e status.', $labels);
        $this->metrics->observe(
            'http_request_duration_seconds',
            'Duração das requisições HTTP.',
            $seconds,
            ['method' => $labels['method'], 'route' => $route],
            self::DURATION_BUCKETS,
        );

        if ($route !== 'metrics') {
            $this->logger->info('http_request', $labels + ['duration_ms' => round($seconds * 1000, 2)]);
        }

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');

        return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();
    }

    /**
     * @return array<string, string>
     */
    private function traceHeaders(Request $request): array
    {
        return array_filter([
            'traceparent' => (string) $request->headers->get('traceparent', ''),
            'tracestate' => (string) $request->headers->get('tracestate', ''),
        ], fn (string $v): bool => $v !== '');
    }

    /**
     * Propaga o trace de quem chamou (gateway, front-end) ou inicia um novo.
     */
    private function traceId(Request $request): string
    {
        $traceparent = (string) $request->headers->get('traceparent', '');

        return preg_match(self::TRACEPARENT, $traceparent, $match) === 1 ? $match[1] : bin2hex(random_bytes(16));
    }
}
