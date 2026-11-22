<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Billing\Payments\AmqpPaymentEventConsumer;
use App\Modules\Billing\Payments\PaymentEventConsumer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\NullLogger;

/**
 * Dublê do broker: entrega as mensagens publicadas ao handler usando a mesma decisão de ack,
 * DLQ e reentrega do consumidor AMQP real (`AmqpPaymentEventConsumer::process`). Não há rede.
 */
final class InMemoryPaymentEventConsumer implements PaymentEventConsumer
{
    /** @var list<string> */
    private array $pending = [];

    /** @var array<string, int> */
    public array $outcomes = ['acked' => 0, 'dead_lettered' => 0, 'requeued' => 0];

    public function publish(string $body): void
    {
        $this->pending[] = $body;
    }

    public function run(callable $handler): void
    {
        $real = new AmqpPaymentEventConsumer(
            ['amqp_url' => 'amqp://dublê', 'exchange' => 'x', 'binding_key' => 'x', 'queue' => 'x', 'delivery_limit' => 1, 'prefetch' => 1],
            new NullLogger,
        );

        foreach (array_splice($this->pending, 0) as $tag => $body) {
            $message = new AMQPMessage($body);
            $message->setChannel(new class extends AMQPChannel
            {
                public function __construct() {}

                public function basic_ack($delivery_tag, $multiple = false): void {}

                public function basic_nack($delivery_tag, $multiple = false, $requeue = false): void {}
            });
            $message->setDeliveryInfo($tag + 1, false, 'payments.events', 'ledger.entry_recorded');

            $this->outcomes[$real->process($message, $handler)]++;
        }
    }
}
