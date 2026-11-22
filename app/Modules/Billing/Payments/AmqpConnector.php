<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

use InvalidArgumentException;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class AmqpConnector
{
    /**
     * Sem heartbeat de propósito: o consumidor espera em laço dentro do handler (backoff de infraestrutura) e
     * um heartbeat não respondido derrubaria a conexão no meio da espera.
     */
    public static function open(string $url): AbstractConnection
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('PAYMENTS_AMQP_URL inválida.');
        }

        $user = isset($parts['user']) ? urldecode($parts['user']) : 'guest';
        $pass = isset($parts['pass']) ? urldecode($parts['pass']) : 'guest';
        $vhost = isset($parts['path']) && $parts['path'] !== '/' ? urldecode(ltrim($parts['path'], '/')) : '/';

        if (($parts['scheme'] ?? 'amqp') === 'amqps') {
            return new AMQPSSLConnection($parts['host'], $parts['port'] ?? 5671, $user, $pass, $vhost, ['verify_peer' => true, 'verify_peer_name' => true]);
        }

        return new AMQPStreamConnection($parts['host'], $parts['port'] ?? 5672, $user, $pass, $vhost);
    }
}
