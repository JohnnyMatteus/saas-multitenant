<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Modules\Observability\Tracing\TracerProviderFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class TracerProviderFactoryTest extends TestCase
{
    #[Test]
    public function without_an_endpoint_the_tracer_is_a_noop(): void
    {
        $this->assertInstanceOf(NoopTracerProvider::class, TracerProviderFactory::make(['endpoint' => null]));
        $this->assertInstanceOf(NoopTracerProvider::class, TracerProviderFactory::make(['endpoint' => '  ']));
    }

    #[Test]
    public function spans_are_posted_as_otlp_json_to_the_collector_with_the_service_name(): void
    {
        /** @var list<RequestInterface> $sent */
        $sent = [];
        $recorder = function (RequestInterface $request) use (&$sent): FulfilledPromise {
            $sent[] = $request;

            return new FulfilledPromise(new Response(200, [], '{}'));
        };
        $provider = TracerFactoryHelper::provider(new Client(['handler' => $recorder]));

        $provider->getTracer('teste')->spanBuilder('scheduling.exemplo')->startSpan()->end();
        $provider->forceFlush();

        $this->assertCount(1, $sent);
        $request = $sent[0];
        $this->assertSame('http://collector.test:4318/v1/traces', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        $this->assertStringContainsString('scheduling.exemplo', $body);
        $this->assertStringContainsString('saas-teste', $body);
    }

    #[Test]
    public function an_unreachable_collector_does_not_break_the_caller(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(503), new Response(503)]));
        $provider = TracerFactoryHelper::provider(new Client(['handler' => $stack]));

        $provider->getTracer('teste')->spanBuilder('x')->startSpan()->end();

        $this->assertInstanceOf(TracerProvider::class, $provider);
        $provider->forceFlush();
        $this->addToAssertionCount(1);
    }
}

final class TracerFactoryHelper
{
    public static function provider(Client $client): TracerProvider
    {
        $provider = TracerProviderFactory::make(
            ['endpoint' => 'http://collector.test:4318/', 'service_name' => 'saas-teste', 'sample_ratio' => 1.0, 'timeout_seconds' => 1],
            $client,
        );
        assert($provider instanceof TracerProvider);

        return $provider;
    }
}
