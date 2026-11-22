<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use App\Modules\Billing\Domain\PaymentNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaymentNotificationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function body(array $overrides = []): string
    {
        return (string) json_encode(array_merge([
            'schema_version' => 1,
            'event_id' => 'evt_01',
            'transaction_id' => 'txn_01',
            'payment_id' => 'pay_01',
            'direction' => 'credit',
            'amount_cents' => 15000,
            'currency' => 'BRL',
            'occurred_at' => '2026-11-20T12:00:00Z',
        ], $overrides));
    }

    #[Test]
    public function it_reads_the_integration_event_of_the_payment_processor(): void
    {
        $event = PaymentNotification::fromJson(self::body());

        $this->assertSame('evt_01', $event->eventId);
        $this->assertSame('pay_01', $event->paymentId);
        $this->assertSame(15000, $event->amountCents);
        $this->assertSame('2026-11-20T12:00:00+00:00', $event->occurredAt->toIso8601String());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'não é json' => ['{oops'];
        yield 'não é objeto' => ['"texto"'];
        yield 'versão futura' => [self::body(['schema_version' => 2])];
        yield 'sem event_id' => [self::body(['event_id' => ''])];
        yield 'payment_id numérico' => [self::body(['payment_id' => 7])];
        yield 'direção desconhecida' => [self::body(['direction' => 'sideways'])];
        yield 'valor zero' => [self::body(['amount_cents' => 0])];
        yield 'valor decimal' => [self::body(['amount_cents' => 10.5])];
        yield 'valor em texto' => [self::body(['amount_cents' => '15000'])];
        yield 'moeda minúscula' => [self::body(['currency' => 'brl'])];
        yield 'data inválida' => [self::body(['occurred_at' => 'ontem'])];
    }

    #[Test]
    #[DataProvider('violations')]
    public function a_message_outside_the_contract_is_a_permanent_error(string $body): void
    {
        $this->expectException(InvalidPaymentMessage::class);

        PaymentNotification::fromJson($body);
    }
}
