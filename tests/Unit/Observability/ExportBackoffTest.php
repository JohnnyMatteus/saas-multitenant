<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use App\Modules\Observability\Tracing\ExportBackoff;
use App\Modules\Observability\Tracing\TracerProviderFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class ExportBackoffTest extends TestCase
{
    #[Test]
    public function a_down_collector_costs_one_attempt_and_one_log_line_per_window_not_one_per_request(): void
    {
        $attempts = 0;
        $client = new Client(['handler' => function (RequestInterface $request) use (&$attempts): RejectedPromise {
            $attempts++;

            return new RejectedPromise(new ConnectException('Could not resolve host', $request));
        }]);
        $logger = new RecordingLogger;
        $backoff = new ExportBackoff(new Repository(new ArrayStore), $logger);
        $provider = TracerProviderFactory::sdk(['endpoint' => 'http://collector.invalid:4318'], $client, $backoff);

        $provider->getTracer('t')->spanBuilder('a')->startSpan()->end();
        $provider->forceFlush();
        $attemptsInFirstFlush = $attempts;

        for ($i = 0; $i < 5; $i++) {
            $provider->getTracer('t')->spanBuilder('b')->startSpan()->end();
            $provider->forceFlush();
        }

        $this->assertGreaterThan(0, $attemptsInFirstFlush);
        $this->assertSame($attemptsInFirstFlush, $attempts, 'Com a janela aberta nenhuma nova tentativa é feita.');
        $this->assertTrue($backoff->isOpen());
        $this->assertSame(['otel_export_failed'], $logger->messages);
    }

    #[Test]
    public function a_healthy_collector_never_opens_the_window(): void
    {
        $client = new Client(['handler' => fn (RequestInterface $r): FulfilledPromise => new FulfilledPromise(new Response(200, [], '{}'))]);
        $backoff = new ExportBackoff(new Repository(new ArrayStore), new RecordingLogger);
        $provider = TracerProviderFactory::sdk(['endpoint' => 'http://collector.test:4318'], $client, $backoff);

        $provider->getTracer('t')->spanBuilder('a')->startSpan()->end();
        $provider->forceFlush();

        $this->assertFalse($backoff->isOpen());
    }

    #[Test]
    public function an_exception_while_flushing_is_swallowed_and_logged_once(): void
    {
        $logger = new RecordingLogger;
        $backoff = new ExportBackoff(new Repository(new ArrayStore), $logger);

        for ($i = 0; $i < 3; $i++) {
            $backoff->guard(fn () => throw new RuntimeException('boom'));
        }

        $this->assertSame(['otel_export_failed'], $logger->messages);
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
