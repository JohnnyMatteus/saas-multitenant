<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Consome `ledger.entry_recorded` do processador de pagamentos (RabbitMQ).
 *
 * Fila quorum com DLQ própria e ack manual depois do handler.
 *
 * - Mensagem fora do contrato: DLQ direto (nack sem requeue).
 * - Falha de infraestrutura (banco fora, conexão perdida, deadlock): a mensagem fica sem ack e o consumidor
 *   espera com backoff exponencial (até 30 s) e tenta de novo, sem limite. Nada é devolvido ao broker, então
 *   uma queda do banco não gasta `x-delivery-limit` nem esvazia a fila para a DLQ.
 * - Outra falha: poucas tentativas com backoff e depois DLQ (defeito determinístico não pode travar a fila).
 * O que cai na DLQ volta com `billing:requeue-dead-letters`.
 */
final class AmqpPaymentEventConsumer implements PaymentEventConsumer
{
    public const string ACKED = 'acked';

    public const string DEAD_LETTERED = 'dead_lettered';

    public const int MAX_UNEXPECTED_ATTEMPTS = 3;

    private const int MAX_BACKOFF_SECONDS = 30;

    private readonly Closure $sleep;

    /**
     * @param  array{amqp_url: string, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int}  $config
     * @param  (callable(int): void)|null  $sleep  espera em segundos; os testes injetam um registrador
     */
    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger,
        private readonly FailureClassifier $classifier = new FailureClassifier,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep !== null ? Closure::fromCallable($sleep) : static function (int $seconds): void {
            sleep($seconds);
        };
    }

    public function run(callable $handler): void
    {
        $connection = AmqpConnector::open($this->config['amqp_url']);
        $channel = $connection->channel();

        try {
            self::declareTopology($channel, $this->config);
            $channel->basic_qos(0, $this->config['prefetch'], false);
            $channel->basic_consume(
                $this->config['queue'],
                '',
                false,
                false,
                false,
                false,
                fn (AMQPMessage $message) => $this->process($message, $handler),
            );

            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    /**
     * @param  callable(string): void  $handler
     */
    public function process(AMQPMessage $message, callable $handler): string
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $handler($message->getBody());
            } catch (InvalidPaymentMessage $e) {
                $this->logger->warning('payment_message_dead_lettered', ['reason' => $e->getMessage()]);
                $message->nack(false);

                return self::DEAD_LETTERED;
            } catch (Throwable $e) {
                $infrastructure = $this->classifier->isInfrastructure($e);
                if (! $infrastructure && $attempt >= self::MAX_UNEXPECTED_ATTEMPTS) {
                    $this->logger->error('payment_message_dead_lettered', ['reason' => 'falha repetida', 'exception' => $e::class, 'attempts' => $attempt]);
                    $message->nack(false);

                    return self::DEAD_LETTERED;
                }

                $delay = min(self::MAX_BACKOFF_SECONDS, 2 ** ($attempt - 1));
                $this->logger->warning('payment_message_retry', [
                    'attempt' => $attempt, 'retry_in_seconds' => $delay, 'infrastructure' => $infrastructure, 'exception' => $e::class,
                ]);
                ($this->sleep)($delay);

                continue;
            }

            $message->ack();

            return self::ACKED;
        }
    }

    /**
     * @param  array{amqp_url: string, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int}  $config
     */
    public static function declareTopology(AMQPChannel $channel, array $config): void
    {
        $queue = $config['queue'];
        $dlx = $queue.'.dlx';
        $dlq = self::deadLetterQueue($config);

        // Declara igual ao produtor: ele pode ainda não ter subido.
        $channel->exchange_declare($config['exchange'], 'topic', false, true, false);
        $channel->exchange_declare($dlx, 'direct', false, true, false);
        $channel->queue_declare($dlq, false, true, false, false, false, new AMQPTable(['x-queue-type' => 'quorum']));
        $channel->queue_bind($dlq, $dlx, $dlq);
        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-delivery-limit' => $config['delivery_limit'],
            'x-dead-letter-exchange' => $dlx,
            'x-dead-letter-routing-key' => $dlq,
        ]));
        $channel->queue_bind($queue, $config['exchange'], $config['binding_key']);
    }

    /**
     * @param  array{queue: string}  $config
     */
    public static function deadLetterQueue(array $config): string
    {
        return $config['queue'].'.dlq';
    }
}
