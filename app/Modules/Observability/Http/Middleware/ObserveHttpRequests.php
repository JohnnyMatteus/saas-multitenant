<?php

declare(strict_types=1);

namespace App\Modules\Observability\Http\Middleware;

use App\Modules\Observability\Metrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

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
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        Context::flush();
        $requestId = $this->requestId($request);
        $traceId = $this->traceId($request);
        Context::add(['request_id' => $requestId, 'trace_id' => $traceId]);

        /** @var Response $response */
        $response = $next($request);

        $seconds = (hrtime(true) - $startedAt) / 1e9;
        $route = $request->route()?->uri() ?? 'unmatched';
        $labels = ['method' => $request->method(), 'route' => $route, 'status' => (string) $response->getStatusCode()];

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
     * Propaga o trace de quem chamou (gateway, front-end) ou inicia um novo.
     */
    private function traceId(Request $request): string
    {
        $traceparent = (string) $request->headers->get('traceparent', '');

        return preg_match(self::TRACEPARENT, $traceparent, $match) === 1 ? $match[1] : bin2hex(random_bytes(16));
    }
}
