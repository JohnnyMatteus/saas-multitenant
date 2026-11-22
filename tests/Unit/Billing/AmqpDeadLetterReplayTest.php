<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Payments\AmqpDeadLetterReplay;
use ArrayObject;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AmqpDeadLetterReplayTest extends TestCase
{
    private const array CONFIG = ['amqp_url' => 'amqp://x', 'exchange' => 'payments.events', 'binding_key' => 'ledger.entry_recorded', 'queue' => 'saas-billing', 'delivery_limit' => 5, 'prefetch' => 1];

    /**
     * Canal falso. `$verdict` decide o que o broker faz com a publicação: 'ack', 'nack' ou 'return'
     * (basic.return antes do ack, como acontece com `mandatory` e fila inexistente).
     *
     * @param  list<string>  $dead
     * @param  ArrayObject<int, string>  $log
     */
    private function channel(array $dead, ArrayObject $log, string $verdict = 'ack'): AMQPChannel
    {
        return new class($dead, $log, $verdict) extends AMQPChannel
        {
            private Closure $onNack;

            private Closure $onReturn;

            /**
             * @param  list<string>  $dead
             * @param  ArrayObject<int, string>  $log
             */
            public function __construct(private array $dead, private readonly ArrayObject $log, private readonly string $verdict)
            {
                $this->onNack = static function (): void {};
                $this->onReturn = static function (): void {};
            }

            public function confirm_select($nowait = false): void
            {
                $this->log->append('confirm_select');
            }

            public function set_nack_handler($callback): void
            {
                $this->onNack = Closure::fromCallable($callback);
            }

            public function set_return_listener($callback): void
            {
                $this->onReturn = Closure::fromCallable($callback);
            }

            public function basic_get($queue = '', $no_ack = false, $ticket = null): ?AMQPMessage
            {
                $body = array_shift($this->dead);
                if ($body === null) {
                    return null;
                }
                $message = new AMQPMessage($body);
                $message->setChannel($this);
                $message->setDeliveryInfo(1, false, 'q.dlx', 'q.dlq');
                $this->log->append("get:{$queue}");

                return $message;
            }

            public function basic_publish($msg, $exchange = '', $routing_key = '', $mandatory = false, $immediate = false, $ticket = null): void
            {
                $this->log->append("publish:{$exchange}|{$routing_key}|{$msg->getBody()}|".($mandatory ? 'mandatory' : 'optional'));
            }

            public function wait_for_pending_acks_returns($timeout = 0): void
            {
                $this->log->append('confirm-wait');
                if ($this->verdict === 'nack') {
                    ($this->onNack)(new AMQPMessage('x'));
                }
                if ($this->verdict === 'return') {
                    ($this->onReturn)(312, 'NO_ROUTE', '', 'saas-billing', new AMQPMessage('x'));
                }
            }

            public function basic_ack($delivery_tag, $multiple = false): void
            {
                $this->log->append('ack-dlq');
            }

            public function basic_nack($delivery_tag, $multiple = false, $requeue = false): void
            {
                $this->log->append('nack-dlq:'.($requeue ? 'requeue' : 'drop'));
            }
        };
    }

    #[Test]
    public function messages_return_to_the_consumer_queue_and_leave_the_dlq_only_after_the_broker_confirms(): void
    {
        $log = new ArrayObject;

        $moved = (new AmqpDeadLetterReplay(self::CONFIG))->replayFrom($this->channel(['{"a":1}', '{"b":2}'], $log), 10);

        $this->assertSame(2, $moved);
        $this->assertSame([
            'confirm_select',
            'get:saas-billing.dlq', 'publish:|saas-billing|{"a":1}|mandatory', 'confirm-wait', 'ack-dlq',
            'get:saas-billing.dlq', 'publish:|saas-billing|{"b":2}|mandatory', 'confirm-wait', 'ack-dlq',
        ], $log->getArrayCopy(), 'Exchange padrão (não reentrega a outros consumidores), mandatory, e só depois do confirm sai da DLQ.');
    }

    #[Test]
    public function a_broker_nack_aborts_before_the_dlq_ack_and_keeps_the_message(): void
    {
        $log = new ArrayObject;

        try {
            (new AmqpDeadLetterReplay(self::CONFIG))->replayFrom($this->channel(['{"a":1}', '{"b":2}'], $log, 'nack'), 10);
            $this->fail('Deveria interromper.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('basic.nack', $e->getMessage());
        }

        $this->assertNotContains('ack-dlq', $log->getArrayCopy(), 'A mensagem não pode sair da DLQ.');
        $this->assertContains('nack-dlq:requeue', $log->getArrayCopy());
        $this->assertCount(1, array_filter($log->getArrayCopy(), fn (string $l): bool => str_starts_with($l, 'get:')), 'Para na primeira falha.');
    }

    #[Test]
    public function an_unroutable_message_aborts_before_the_dlq_ack(): void
    {
        $log = new ArrayObject;

        try {
            (new AmqpDeadLetterReplay(self::CONFIG))->replayFrom($this->channel(['{"a":1}'], $log, 'return'), 10);
            $this->fail('Deveria interromper.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('312 NO_ROUTE', $e->getMessage());
        }

        $this->assertNotContains('ack-dlq', $log->getArrayCopy());
        $this->assertContains('nack-dlq:requeue', $log->getArrayCopy());
    }

    #[Test]
    public function the_limit_caps_how_many_messages_move(): void
    {
        $this->assertSame(1, (new AmqpDeadLetterReplay(self::CONFIG))->replayFrom($this->channel(['1', '2', '3'], new ArrayObject), 1));
    }
}
