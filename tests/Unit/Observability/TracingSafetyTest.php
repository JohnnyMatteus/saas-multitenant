<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Modules\Observability\Tracing\TracerProviderFactory;
use App\Modules\Observability\Tracing\Tracing;
use App\Modules\Scheduling\Domain\Exceptions\AlreadyBooked;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class TracingSafetyTest extends TestCase
{
    #[Test]
    public function an_exception_message_with_personal_data_never_reaches_the_exported_body(): void
    {
        $bodies = [];
        $client = new Client(['handler' => function (RequestInterface $request) use (&$bodies): FulfilledPromise {
            $bodies[] = (string) $request->getBody();

            return new FulfilledPromise(new Response(200, [], '{}'));
        }]);
        $provider = TracerProviderFactory::sdk(['endpoint' => 'http://collector.test:4318', 'service_name' => 'saas-teste'], $client);
        $tracing = new Tracing($provider);

        try {
            $tracing->span('scheduling.exemplo', fn () => throw new RuntimeException('Duplicate entry aluna.beatriz@example.com para (11) 99999-0000'));
        } catch (RuntimeException) {
        }
        $provider->forceFlush();

        $this->assertCount(1, $bodies);
        $this->assertStringNotContainsString('aluna.beatriz@example.com', $bodies[0]);
        $this->assertStringNotContainsString('99999-0000', $bodies[0]);
        $this->assertStringNotContainsString('stacktrace', $bodies[0]);
        $this->assertStringContainsString('exception.type', $bodies[0]);
        $this->assertStringContainsString('RuntimeException', $bodies[0]);
    }

    #[Test]
    public function an_unexpected_failure_marks_the_span_as_error(): void
    {
        $exporter = new InMemoryExporter;
        $tracing = new Tracing(new TracerProvider(new SimpleSpanProcessor($exporter), new AlwaysOnSampler));

        try {
            $tracing->span('scheduling.exemplo', fn () => throw new RuntimeException('x'));
        } catch (RuntimeException) {
        }

        $span = $exporter->getSpans()[0];
        $this->assertSame('Error', $span->getStatus()->getCode());
    }

    #[Test]
    public function an_expected_domain_refusal_is_not_an_error_and_records_its_type(): void
    {
        $exporter = new InMemoryExporter;
        $tracing = new Tracing(new TracerProvider(new SimpleSpanProcessor($exporter), new AlwaysOnSampler));

        try {
            $tracing->span('scheduling.exemplo', fn () => throw new AlreadyBooked);
        } catch (AlreadyBooked) {
        }

        $span = $exporter->getSpans()[0];
        $this->assertSame('Unset', $span->getStatus()->getCode());
        $this->assertSame('already_booked', $span->getAttributes()->get('domain_error.type'));
    }
}
