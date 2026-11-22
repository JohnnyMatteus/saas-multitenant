<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Billing\Domain\PaymentOutcome;
use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Billing\Payments\PaymentEventConsumer;
use App\Modules\Identity\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InMemoryPaymentEventConsumer;
use Tests\TestCase;
use Tests\Unit\Billing\PaymentNotificationTest;

/**
 * Entrada real: o comando `billing:consume-payments` com o broker substituído pelo dublê
 * (mesma decisão de ack/DLQ do consumidor AMQP). O banco é o MySQL de verdade.
 */
final class PaymentEventsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    private PackageOrder $order;

    private PackageOrder $foreignOrder;

    private InMemoryPaymentEventConsumer $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
        $this->order = $this->makeOrder($this->lotus, 'pay_lotus_1');
        $this->foreignOrder = $this->makeOrder($this->equilibrio, 'pay_equilibrio_1');

        $this->broker = new InMemoryPaymentEventConsumer;
        $this->app->instance(PaymentEventConsumer::class, $this->broker);
    }

    private function makeOrder(Tenant $tenant, string $paymentId): PackageOrder
    {
        $member = $this->createUser($tenant, Role::Student);
        $package = LessonPackage::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Pacote', 'lesson_count' => 8, 'price_cents' => 15000, 'currency' => 'BRL', 'active' => true,
        ]);

        return PackageOrder::query()->create([
            'tenant_id' => $tenant->id, 'package_id' => $package->id, 'member_id' => $member->id, 'payment_id' => $paymentId,
            'lesson_count' => 8, 'amount_cents' => 15000, 'currency' => 'BRL', 'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function deliver(array $overrides = []): void
    {
        $this->broker->publish(PaymentNotificationTest::body(['payment_id' => 'pay_lotus_1', ...$overrides]));
        $this->assertSame(0, Artisan::call('billing:consume-payments'));
    }

    private function reload(PackageOrder $order): PackageOrder
    {
        return PackageOrder::withoutTenancy()->findOrFail($order->id);
    }

    #[Test]
    public function a_matching_credit_marks_the_order_as_paid_and_records_the_event(): void
    {
        $this->deliver();

        $order = $this->reload($this->order);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('2026-11-20 12:00:00', $order->paid_at?->format('Y-m-d H:i:s'));
        $event = PaymentEvent::withoutTenancy()->sole();
        $this->assertSame(PaymentOutcome::Paid, $event->outcome);
        $this->assertSame($this->lotus->id, $event->tenant_id);
        $this->assertSame(1, $this->broker->outcomes['acked']);
    }

    #[Test]
    public function the_same_event_delivered_twice_in_one_run_applies_once(): void
    {
        $this->broker->publish(PaymentNotificationTest::body(['payment_id' => 'pay_lotus_1']));
        $this->broker->publish(PaymentNotificationTest::body(['payment_id' => 'pay_lotus_1']));
        $this->assertSame(0, Artisan::call('billing:consume-payments'));

        $this->assertSame(1, PaymentEvent::withoutTenancy()->count());
        $this->assertSame(OrderStatus::Paid, $this->reload($this->order)->status);
        $this->assertSame(2, $this->broker->outcomes['acked'], 'A duplicata é confirmada, não devolvida à fila.');
    }

    #[Test]
    public function a_redelivery_in_a_later_run_does_not_change_anything(): void
    {
        $this->deliver();
        $paidAt = $this->reload($this->order)->paid_at;

        $this->deliver(['occurred_at' => '2026-11-25T08:00:00Z']);

        $this->assertSame(1, PaymentEvent::withoutTenancy()->count());
        $this->assertEquals($paidAt, $this->reload($this->order)->paid_at);
    }

    #[Test]
    public function a_second_credit_with_another_event_id_is_recorded_as_already_paid(): void
    {
        $this->deliver();
        $this->deliver(['event_id' => 'evt_02', 'transaction_id' => 'txn_02']);

        $this->assertSame(2, PaymentEvent::withoutTenancy()->count());
        $this->assertSame(PaymentOutcome::AlreadyPaid, PaymentEvent::withoutTenancy()->where('event_id', 'evt_02')->sole()->outcome);
    }

    #[Test]
    public function an_amount_or_currency_that_does_not_match_the_order_leaves_it_pending(): void
    {
        $this->deliver(['amount_cents' => 14999]);
        $this->deliver(['event_id' => 'evt_02', 'currency' => 'USD']);

        $this->assertSame(OrderStatus::Pending, $this->reload($this->order)->status);
        $this->assertSame(
            [PaymentOutcome::AmountMismatch, PaymentOutcome::AmountMismatch],
            PaymentEvent::withoutTenancy()->orderBy('event_id')->get()->map(fn (PaymentEvent $e) => $e->outcome)->all(),
        );
    }

    #[Test]
    public function a_refund_is_only_recorded_and_changes_neither_the_order_nor_a_paid_state(): void
    {
        $this->deliver();
        $this->deliver(['event_id' => 'evt_refund', 'transaction_id' => 'txn_refund', 'direction' => 'debit']);

        $this->assertSame(OrderStatus::Paid, $this->reload($this->order)->status);
        $this->assertSame(PaymentOutcome::RefundRecorded, PaymentEvent::withoutTenancy()->where('event_id', 'evt_refund')->sole()->outcome);
    }

    #[Test]
    public function an_event_resolves_the_tenant_from_the_order_and_never_touches_another_studio(): void
    {
        $this->deliver(['payment_id' => 'pay_equilibrio_1']);

        $this->assertSame(OrderStatus::Paid, $this->reload($this->foreignOrder)->status);
        $this->assertSame(OrderStatus::Pending, $this->reload($this->order)->status);
        $this->assertSame($this->equilibrio->id, PaymentEvent::withoutTenancy()->sole()->tenant_id);
    }

    #[Test]
    public function an_event_for_an_unknown_payment_is_acknowledged_and_changes_nothing(): void
    {
        $this->deliver(['payment_id' => 'pay_nao_existe']);

        $this->assertSame(0, PaymentEvent::withoutTenancy()->count());
        $this->assertSame(1, $this->broker->outcomes['acked']);
        $this->assertSame(OrderStatus::Pending, $this->reload($this->order)->status);
    }

    #[Test]
    public function a_message_outside_the_contract_is_dead_lettered_and_changes_nothing(): void
    {
        $this->broker->publish('{"nao":"e um evento"}');
        $this->broker->publish(PaymentNotificationTest::body(['payment_id' => 'pay_lotus_1', 'amount_cents' => -5]));
        $this->assertSame(0, Artisan::call('billing:consume-payments'));

        $this->assertSame(2, $this->broker->outcomes['dead_lettered']);
        $this->assertSame(0, PaymentEvent::withoutTenancy()->count());
        $this->assertSame(OrderStatus::Pending, $this->reload($this->order)->status);
    }

    #[Test]
    public function a_database_outage_over_many_attempts_dead_letters_nothing_and_the_order_is_paid_when_it_returns(): void
    {
        $failures = 7; // acima do x-delivery-limit (5): se a falha virasse devolução ao broker, iria para a DLQ
        DB::beforeExecuting(function () use (&$failures): void {
            if ($failures > 0) {
                $failures--;

                throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
            }
        });

        $this->deliver();

        $this->assertSame(0, $failures);
        $this->assertSame(0, $this->broker->outcomes['dead_lettered']);
        $this->assertSame(1, $this->broker->outcomes['acked']);
        $this->assertCount(7, $this->broker->backoffs);
        $this->assertSame(OrderStatus::Paid, $this->reload($this->order)->status);
        $this->assertSame(1, PaymentEvent::withoutTenancy()->count());
    }

    #[Test]
    public function processing_an_event_leaves_no_tenant_in_the_context(): void
    {
        $this->deliver();

        $this->assertNull($this->app->make(TenantContext::class)->currentOrNull());
    }

    #[Test]
    public function the_command_fails_clearly_when_the_broker_is_not_configured(): void
    {
        $this->app->forgetInstance(PaymentEventConsumer::class);
        $this->app->bind(PaymentEventConsumer::class, fn () => throw new \LogicException('Defina PAYMENTS_AMQP_URL'));

        $this->assertSame(1, Artisan::call('billing:consume-payments'));
        $this->assertStringContainsString('PAYMENTS_AMQP_URL', Artisan::output());
    }
}
