<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Modules\Observability\Tracing\Tracing;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class TracingTest extends TestCase
{
    private InMemoryExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new InMemoryExporter;
        $provider = new TracerProvider(new SimpleSpanProcessor($this->exporter), new AlwaysOnSampler);
        $this->app->instance(TracerProviderInterface::class, $provider);
        $this->app->forgetInstance(Tracing::class);
    }

    #[Test]
    public function the_request_span_continues_the_callers_trace_and_matches_the_logged_trace_id(): void
    {
        $traceId = '4bf92f3577b34da6a3ce929d0e0e4736';

        $response = $this->withHeader('traceparent', "00-{$traceId}-00f067aa0ba902b7-01")->getJson('/api/v1/me');

        $response->assertUnauthorized()->assertHeader('X-Trace-Id', $traceId);
        $spans = $this->exporter->getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame($traceId, $spans[0]->getTraceId());
        $this->assertSame('00f067aa0ba902b7', $spans[0]->getParentSpanId());
        $this->assertSame('GET /api/v1/me', $spans[0]->getName());
        $this->assertSame(401, $spans[0]->getAttributes()->get('http.response.status_code'));
        $this->assertSame('api/v1/me', $spans[0]->getAttributes()->get('http.route'));
    }

    #[Test]
    public function a_request_without_traceparent_starts_a_new_trace_and_exposes_its_id(): void
    {
        $response = $this->getJson('/api/v1/me');

        $span = $this->exporter->getSpans()[0];
        $response->assertHeader('X-Trace-Id', $span->getTraceId());
        $this->assertSame('0000000000000000', $span->getParentSpanId());
    }

    #[Test]
    public function an_internal_span_records_the_error_and_rethrows(): void
    {
        $tracing = $this->app->make(Tracing::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('falhou');

        try {
            $tracing->span('scheduling.exemplo', fn () => throw new RuntimeException('falhou'), ['session.id' => 'abc']);
        } finally {
            $this->assertRecordedFailure();
        }
    }

    private function assertRecordedFailure(): void
    {
        $span = $this->exporter->getSpans()[0];
        $this->assertSame('scheduling.exemplo', $span->getName());
        $this->assertSame('Error', $span->getStatus()->getCode());
        $this->assertSame('exception', $span->getEvents()[0]->getName());
    }
}
