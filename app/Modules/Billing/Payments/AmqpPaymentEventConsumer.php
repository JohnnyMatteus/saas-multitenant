<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Consome `ledger.entry_recorded` do processador de pagamentos (RabbitMQ).
 *
 * Fila quorum com `x-delivery-limit` e DLQ própria; ack manual depois do handler. Mensagem fora do
 * contrato vai direto para a DLQ (nack sem requeue); falha transitória é devolvida e o próprio
 * broker a descarta para a DLQ depois do limite de entregas.
 */
final class AmqpPaymentEventConsumer implements PaymentEventConsumer
{
    public const string ACKED = 'acked';

    public const string DEAD_LETTERED = 'dead_lettered';

    public const string REQUEUED = 'requeued';

    /**
     * @param  array{amqp_url: string, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int}  $config
     */
    public function __construct(private readonly array $config, private readonly LoggerInterface $logger) {}

    public function run(callable $handler): void
    {
        $connection = $this->connect();
        $channel = $connection->channel();

        try {
            $this->declareTopology($channel);
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
        try {
            $handler($message->getBody());
        } catch (InvalidPaymentMessage $e) {
            $this->logger->warning('payment_message_dead_lettered', ['reason' => $e->getMessage()]);
            $message->nack(false);

            return self::DEAD_LETTERED;
        } catch (Throwable $e) {
            $this->logger->error('payment_message_failed', ['exception' => $e::class]);
            $message->nack(true);

            return self::REQUEUED;
        }

        $message->ack();

        return self::ACKED;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $queue = $this->config['queue'];
        $dlx = $queue.'.dlx';
        $dlq = $queue.'.dlq';

        // Passiva demais não serve: o produtor pode ainda não ter subido, então declara igual a ele.
        $channel->exchange_declare($this->config['exchange'], 'topic', false, true, false);
        $channel->exchange_declare($dlx, 'direct', false, true, false);
        $channel->queue_declare($dlq, false, true, false, false, false, new AMQPTable(['x-queue-type' => 'quorum']));
        $channel->queue_bind($dlq, $dlx, $dlq);
        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-delivery-limit' => $this->config['delivery_limit'],
            'x-dead-letter-exchange' => $dlx,
            'x-dead-letter-routing-key' => $dlq,
        ]));
        $channel->queue_bind($queue, $this->config['exchange'], $this->config['binding_key']);
    }

    private function connect(): AbstractConnection
    {
        $url = parse_url($this->config['amqp_url']);
        if ($url === false || ! isset($url['host'])) {
            throw new \InvalidArgumentException('PAYMENTS_AMQP_URL inválida.');
        }

        $host = $url['host'];
        $user = isset($url['user']) ? urldecode($url['user']) : 'guest';
        $pass = isset($url['pass']) ? urldecode($url['pass']) : 'guest';
        $vhost = isset($url['path']) && $url['path'] !== '/' ? urldecode(ltrim($url['path'], '/')) : '/';

        if (($url['scheme'] ?? 'amqp') === 'amqps') {
            return new AMQPSSLConnection($host, $url['port'] ?? 5671, $user, $pass, $vhost, ['verify_peer' => true, 'verify_peer_name' => true]);
        }

        return new AMQPStreamConnection($host, $url['port'] ?? 5672, $user, $pass, $vhost);
    }
}
