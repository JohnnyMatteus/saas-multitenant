<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Payments\AmqpDeadLetterReplay;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

final class RequeueDeadLetters extends Command
{
    protected $signature = 'billing:requeue-dead-letters {--limit=100 : Máximo de mensagens a devolver}';

    protected $description = 'Devolve à fila as mensagens de pagamento que foram para a DLQ';

    public function handle(Config $config): int
    {
        /** @var array{amqp_url: string|null, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int} $settings */
        $settings = $config->get('billing.payments');
        if (! is_string($settings['amqp_url']) || $settings['amqp_url'] === '') {
            $this->error('Defina PAYMENTS_AMQP_URL.');

            return self::FAILURE;
        }

        $moved = (new AmqpDeadLetterReplay([...$settings, 'amqp_url' => $settings['amqp_url']]))->replay(max(1, (int) $this->option('limit')));
        $this->info("{$moved} mensagem(ns) devolvida(s) à fila.");

        return self::SUCCESS;
    }
}
