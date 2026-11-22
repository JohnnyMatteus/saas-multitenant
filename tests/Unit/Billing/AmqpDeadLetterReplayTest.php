<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Payments\AmqpDeadLetterReplay;
use ArrayObject;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AmqpDeadLetterReplayTest extends TestCase
{
    /**
     * @param  list<string>  $dead
     * @param  ArrayObject<int, string>  $log
     */
    private function channel(array $dead, ArrayObject $log): AMQPChannel
    {
        return new class($dead, $log) extends AMQPChannel
        {
            /**
             * @param  list<string>  $dead
             * @param  ArrayObject<int, string>  $log
             */
            public function __construct(private array $dead, private readonly ArrayObject $log) {}

            public function confirm_select($nowait = false): void
            {
                $this->log->append('confirm_select');
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
                $this->log->append("publish:{$exchange}|{$routing_key}|".$msg->getBody());
            }

            public function wait_for_pending_acks($timeout = 0): void
            {
                $this->log->append('confirmed');
            }

            public function basic_ack($delivery_tag, $multiple = false): void
            {
                $this->log->append('ack-dlq');
            }
        };
    }

    #[Test]
    public function messages_return_to_the_consumer_queue_and_leave_the_dlq_only_after_the_broker_confirms(): void
    {
        $log = new ArrayObject;
        $replay = new AmqpDeadLetterReplay(['amqp_url' => 'amqp://x', 'exchange' => 'payments.events', 'binding_key' => 'ledger.entry_recorded', 'queue' => 'saas-billing', 'delivery_limit' => 5, 'prefetch' => 1]);

        $moved = $replay->replayFrom($this->channel(['{"a":1}', '{"b":2}'], $log), 10);

        $this->assertSame(2, $moved);
        $this->assertSame([
            'confirm_select',
            'get:saas-billing.dlq', 'publish:|saas-billing|{"a":1}', 'confirmed', 'ack-dlq',
            'get:saas-billing.dlq', 'publish:|saas-billing|{"b":2}', 'confirmed', 'ack-dlq',
        ], $log->getArrayCopy(), 'Publica na fila pela exchange padrão (não reentrega a outros consumidores) e só então confirma a saída da DLQ.');
    }

    #[Test]
    public function the_limit_caps_how_many_messages_move(): void
    {
        $log = new ArrayObject;
        $replay = new AmqpDeadLetterReplay(['amqp_url' => 'amqp://x', 'exchange' => 'e', 'binding_key' => 'k', 'queue' => 'q', 'delivery_limit' => 5, 'prefetch' => 1]);

        $this->assertSame(1, $replay->replayFrom($this->channel(['1', '2', '3'], $log), 1));
    }
}
