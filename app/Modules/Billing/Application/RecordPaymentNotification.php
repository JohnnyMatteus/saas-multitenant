<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Billing\Domain\PaymentNotification;
use App\Modules\Billing\Domain\PaymentOutcome;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Observability\Metrics;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

/**
 * Aplica um evento do processador de pagamentos, no máximo uma vez por `event_id`.
 *
 * O evento não traz o estúdio: o tenant sai do pedido que tem aquele `payment_id`
 * (código de sistema, única consulta sem tenant do módulo) e o resto roda como esse tenant.
 *
 * Efeitos: crédito com valor e moeda do pedido marca-o como pago; qualquer outro caso só é
 * registrado. Estorno (`debit`) não altera pedido nem créditos: a política de estorno e de
 * consumo de créditos ainda não foi definida pelo autor.
 */
final readonly class RecordPaymentNotification
{
    public function __construct(
        private ConnectionInterface $db,
        private TenantContext $tenantContext,
        private Metrics $metrics,
        private LoggerInterface $logger,
    ) {}

    public function handle(PaymentNotification $event): PaymentOutcome
    {
        // Sistema: o evento chega sem tenant, é o pedido que o revela.
        $found = PackageOrder::withoutTenancy()->where('payment_id', $event->paymentId)->first();
        if ($found === null) {
            $this->logger->notice('payment_event_unknown_payment', ['event_id' => $event->eventId, 'payment_id' => $event->paymentId]);

            return $this->count(PaymentOutcome::UnknownPayment);
        }

        $tenant = Tenant::query()->findOrFail($found->tenant_id);

        try {
            $outcome = $this->tenantContext->runAs($tenant, fn (): PaymentOutcome => $this->apply($found->id, $event));
        } catch (UniqueConstraintViolationException) {
            // Entrega paralela do mesmo evento perdeu a corrida pela unique; a outra já aplicou.
            $outcome = PaymentOutcome::Duplicate;
        }

        return $this->count($outcome);
    }

    private function apply(string $orderId, PaymentNotification $event): PaymentOutcome
    {
        return $this->db->transaction(function () use ($orderId, $event): PaymentOutcome {
            // Serializa eventos do mesmo pedido.
            $order = PackageOrder::query()->lockForUpdate()->findOrFail($orderId);

            if (PaymentEvent::query()->where('event_id', $event->eventId)->exists()) {
                return PaymentOutcome::Duplicate;
            }

            $outcome = $this->decide($order, $event);
            if ($outcome === PaymentOutcome::Paid) {
                $order->fill(['status' => OrderStatus::Paid, 'paid_at' => $event->occurredAt])->save();
            }

            PaymentEvent::query()->create([
                'order_id' => $order->id,
                'event_id' => $event->eventId,
                'transaction_id' => $event->transactionId,
                'direction' => $event->direction,
                'amount_cents' => $event->amountCents,
                'currency' => $event->currency,
                'occurred_at' => $event->occurredAt,
                'outcome' => $outcome,
            ]);

            return $outcome;
        });
    }

    private function decide(PackageOrder $order, PaymentNotification $event): PaymentOutcome
    {
        if ($event->direction === 'debit') {
            return PaymentOutcome::RefundRecorded;
        }

        if ($event->amountCents !== $order->amount_cents || $event->currency !== $order->currency) {
            return PaymentOutcome::AmountMismatch;
        }

        return $order->status === OrderStatus::Paid ? PaymentOutcome::AlreadyPaid : PaymentOutcome::Paid;
    }

    private function count(PaymentOutcome $outcome): PaymentOutcome
    {
        $this->metrics->increment('billing_payment_events_total', 'Eventos de pagamento por resultado.', ['outcome' => $outcome->value]);

        return $outcome;
    }
}
