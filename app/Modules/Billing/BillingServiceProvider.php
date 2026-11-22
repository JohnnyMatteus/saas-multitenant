<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Console\ConsumePaymentEvents;
use App\Modules\Billing\Console\RequeueDeadLetters;
use App\Modules\Billing\Payments\AmqpPaymentEventConsumer;
use App\Modules\Billing\Payments\PaymentEventConsumer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Psr\Log\LoggerInterface;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentEventConsumer::class, function (Application $app): PaymentEventConsumer {
            /** @var array{amqp_url: string|null, exchange: string, binding_key: string, queue: string, delivery_limit: int, prefetch: int} $config */
            $config = $app->make(Config::class)->get('billing.payments');
            if (! is_string($config['amqp_url']) || $config['amqp_url'] === '') {
                throw new LogicException('Defina PAYMENTS_AMQP_URL para consumir os eventos do processador de pagamentos.');
            }

            return new AmqpPaymentEventConsumer([...$config, 'amqp_url' => $config['amqp_url']], $app->make(LoggerInterface::class));
        });
    }

    public function boot(): void
    {
        Gate::define('manage-lesson-packages', fn (User $user): bool => $user->hasRole(Role::StudioAdmin));
        Gate::define('purchase-lesson-packages', fn (User $user): bool => $user->hasRole(Role::Student));

        $this->commands([ConsumePaymentEvents::class, RequeueDeadLetters::class]);
    }
}
