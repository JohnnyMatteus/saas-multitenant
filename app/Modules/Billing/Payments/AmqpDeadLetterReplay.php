<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Devolve as mensagens da DLQ para a fila do consumidor, depois de corrigida a causa (contrato ou defeito).
 * Publica direto na fila pela exchange padrão, não na exchange do processador, para não reentregar a
 * mensagem a outros consumidores. A mensagem só sai da DLQ depois do confirm do broker: nada se perde.
 * Uma mensagem ainda inválida simplesmente volta para a DLQ quando for consumida.
 */
final readonly class AmqpDeadLetterReplay
{
    /**
     * @param  array{amqp_url: string, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int}  $config
     */
    public function __construct(private array $config) {}

    public function replay(int $limit): int
    {
        $connection = AmqpConnector::open($this->config['amqp_url']);
        $channel = $connection->channel();

        try {
            AmqpPaymentEventConsumer::declareTopology($channel, $this->config);

            return $this->replayFrom($channel, $limit);
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    public function replayFrom(AMQPChannel $channel, int $limit): int
    {
        $dlq = AmqpPaymentEventConsumer::deadLetterQueue($this->config);
        $channel->confirm_select();
        $moved = 0;

        while ($moved < $limit) {
            $message = $channel->basic_get($dlq, false);
            if (! $message instanceof AMQPMessage) {
                break;
            }

            $channel->basic_publish(new AMQPMessage($message->getBody(), ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]), '', $this->config['queue']);
            $channel->wait_for_pending_acks();
            $message->ack();
            $moved++;
        }

        return $moved;
    }
}
