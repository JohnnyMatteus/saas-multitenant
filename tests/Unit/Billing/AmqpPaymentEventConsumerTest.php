<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use App\Modules\Billing\Payments\AmqpPaymentEventConsumer;
use ArrayObject;
use PDOException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class AmqpPaymentEventConsumerTest extends TestCase
{
    /** @var ArrayObject<int, string> */
    private ArrayObject $calls;

    protected function setUp(): void
    {
        $this->calls = new ArrayObject;
    }

    private function message(): AMQPMessage
    {
        $message = new AMQPMessage('{}');
        $message->setChannel(new class($this->calls) extends AMQPChannel
        {
            /** @param ArrayObject<int, string> $calls */
            public function __construct(private readonly ArrayObject $calls) {}

            public function basic_ack($delivery_tag, $multiple = false): void
            {
                $this->calls->append('ack');
            }

            public function basic_nack($delivery_tag, $multiple = false, $requeue = false): void
            {
                $this->calls->append($requeue ? 'nack-requeue' : 'nack-dlq');
            }
        });
        $message->setDeliveryInfo(1, false, 'payments.events', 'ledger.entry_recorded');

        return $message;
    }

    /** @var list<int> */
    private array $sleeps = [];

    private function consumer(): AmqpPaymentEventConsumer
    {
        return new AmqpPaymentEventConsumer(
            ['amqp_url' => 'amqp://x', 'exchange' => 'e', 'binding_key' => 'k', 'queue' => 'q', 'delivery_limit' => 5, 'prefetch' => 1],
            new NullLogger,
            sleep: function (int $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    #[Test]
    public function a_handled_message_is_acknowledged_only_after_the_handler_returns(): void
    {
        $result = $this->consumer()->process($this->message(), function (): void {
            $this->assertSame([], $this->calls->getArrayCopy(), 'Nada de ack antes do handler terminar.');
        });

        $this->assertSame(AmqpPaymentEventConsumer::ACKED, $result);
        $this->assertSame(['ack'], $this->calls->getArrayCopy());
    }

    #[Test]
    public function a_message_outside_the_contract_goes_to_the_dead_letter_queue_without_retry(): void
    {
        $result = $this->consumer()->process($this->message(), fn () => throw InvalidPaymentMessage::because('teste'));

        $this->assertSame(AmqpPaymentEventConsumer::DEAD_LETTERED, $result);
        $this->assertSame(['nack-dlq'], $this->calls->getArrayCopy());
        $this->assertSame([], $this->sleeps);
    }

    #[Test]
    public function an_infrastructure_outage_never_returns_the_message_to_the_broker_or_dead_letters_it(): void
    {
        $failures = 12; // bem acima do x-delivery-limit (5) e de qualquer limite de tentativas
        $result = $this->consumer()->process($this->message(), function () use (&$failures): void {
            if ($failures-- > 0) {
                throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
            }
        });

        $this->assertSame(AmqpPaymentEventConsumer::ACKED, $result);
        // Um ack só, no fim: nada de nack/requeue durante a queda, então nenhuma entrega é gasta.
        $this->assertSame(['ack'], $this->calls->getArrayCopy());
        $this->assertSame([1, 2, 4, 8, 16, 30, 30, 30, 30, 30, 30, 30], $this->sleeps, 'Backoff exponencial com teto de 30 s.');
    }

    #[Test]
    public function a_deadlock_is_also_treated_as_infrastructure(): void
    {
        $failures = 5;
        $result = $this->consumer()->process($this->message(), function () use (&$failures): void {
            if ($failures-- > 0) {
                throw new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock');
            }
        });

        $this->assertSame(AmqpPaymentEventConsumer::ACKED, $result);
    }

    #[Test]
    public function an_unexpected_failure_is_retried_a_few_times_and_then_dead_lettered(): void
    {
        $attempts = 0;
        $result = $this->consumer()->process($this->message(), function () use (&$attempts): void {
            $attempts++;

            throw new RuntimeException('defeito do código');
        });

        $this->assertSame(AmqpPaymentEventConsumer::DEAD_LETTERED, $result);
        $this->assertSame(AmqpPaymentEventConsumer::MAX_UNEXPECTED_ATTEMPTS, $attempts);
        $this->assertSame(['nack-dlq'], $this->calls->getArrayCopy());
    }
}
