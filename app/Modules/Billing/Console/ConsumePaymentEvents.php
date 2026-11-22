<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Application\HandlePaymentMessage;
use App\Modules\Billing\Payments\PaymentEventConsumer;
use Illuminate\Console\Command;
use Throwable;

final class ConsumePaymentEvents extends Command
{
    protected $signature = 'billing:consume-payments';

    protected $description = 'Consome os eventos do processador de pagamentos e atualiza os pedidos de pacote';

    public function handle(HandlePaymentMessage $handler): int
    {
        try {
            $consumer = $this->laravel->make(PaymentEventConsumer::class);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $consumer->run($handler(...));

        return self::SUCCESS;
    }
}
