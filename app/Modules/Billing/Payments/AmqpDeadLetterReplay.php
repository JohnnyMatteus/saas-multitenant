<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

/**
 * Devolve as mensagens da DLQ para a fila do consumidor, depois de corrigida a causa (contrato ou defeito).
 * Publica direto na fila pela exchange padrão, não na exchange do processador, para não reentregar a
 * mensagem a outros consumidores. A mensagem só sai da DLQ depois do confirm do broker: nada se perde.
 * Uma mensagem ainda inválida simplesmente volta para a DLQ quando for consumida.
 */
final readonly class AmqpDeadLetterReplay
{
    private const int CONFIRM_TIMEOUT_SECONDS = 10;

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

    /**
     * Só tira a mensagem da DLQ depois de o broker confirmá-la e de ela ter sido roteada: `basic.nack` e
     * `basic.return` (fila inexistente, com `mandatory`) NÃO fazem `wait_for_pending_acks` lançar, então
     * são capturados por handler e abortam antes do ack. A mensagem volta para a DLQ.
     *
     * @throws RuntimeException se o broker recusou ou não roteou a mensagem
     */
    public function replayFrom(AMQPChannel $channel, int $limit): int
    {
        $dlq = AmqpPaymentEventConsumer::deadLetterQueue($this->config);
        $verdict = new BrokerRefusal;

        $channel->confirm_select();
        $channel->set_nack_handler(function () use ($verdict): void {
            $verdict->record('o broker recusou a mensagem (basic.nack)');
        });
        $channel->set_return_listener(function (int $code, string $text) use ($verdict): void {
            $verdict->record("a mensagem não foi roteada para a fila ({$code} {$text})");
        });

        $moved = 0;
        while ($moved < $limit) {
            $message = $channel->basic_get($dlq, false);
            if (! $message instanceof AMQPMessage) {
                break;
            }

            $verdict->clear();
            $channel->basic_publish(
                new AMQPMessage($message->getBody(), ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
                '',
                $this->config['queue'],
                true,
            );
            $channel->wait_for_pending_acks_returns(self::CONFIRM_TIMEOUT_SECONDS);

            $refusal = $verdict->reason();
            if ($refusal !== null) {
                $message->nack(true);

                throw new RuntimeException("Reprocessamento interrompido após {$moved} mensagem(ns): {$refusal}. A mensagem continua na DLQ.");
            }

            $message->ack();
            $moved++;
        }

        return $moved;
    }
}
