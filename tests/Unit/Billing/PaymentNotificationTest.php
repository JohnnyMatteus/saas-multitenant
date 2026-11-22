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
        yield 'data em texto livre' => [self::body(['occurred_at' => 'ontem'])];
        yield 'data relativa' => [self::body(['occurred_at' => 'tomorrow'])];
        yield 'data relativa agora' => [self::body(['occurred_at' => 'now'])];
        yield 'data vazia' => [self::body(['occurred_at' => ''])];
        yield 'data ausente' => [(string) json_encode(array_diff_key(json_decode(self::body(), true), ['occurred_at' => 1]))];
        yield 'data nula' => [self::body(['occurred_at' => null])];
        yield 'data numérica' => [self::body(['occurred_at' => 1790000000])];
        yield 'data como lista' => [self::body(['occurred_at' => ['2026-11-20T12:00:00Z']])];
        yield 'só a data' => [self::body(['occurred_at' => '2026-11-20'])];
        yield 'sem fuso' => [self::body(['occurred_at' => '2026-11-20T12:00:00'])];
        yield 'separador com espaço' => [self::body(['occurred_at' => '2026-11-20 12:00:00Z'])];
        yield 'dia que não existe' => [self::body(['occurred_at' => '2026-02-30T12:00:00Z'])];
        yield 'hora 25' => [self::body(['occurred_at' => '2026-11-20T25:00:00Z'])];
    }

    #[Test]
    public function the_instant_accepts_fraction_and_offsets_and_is_stored_in_utc(): void
    {
        $nano = PaymentNotification::fromJson(self::body(['occurred_at' => '2026-11-20T12:00:00.123456789Z']));
        $offset = PaymentNotification::fromJson(self::body(['occurred_at' => '2026-11-20T09:00:00-03:00']));

        $this->assertSame('2026-11-20 12:00:00.123456', $nano->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-11-20 12:00:00', $offset->occurredAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $offset->occurredAt->getTimezone()->getName());
    }

    #[Test]
    #[DataProvider('violations')]
    public function a_message_outside_the_contract_is_a_permanent_error(string $body): void
    {
        $this->expectException(InvalidPaymentMessage::class);

        PaymentNotification::fromJson($body);
    }
}
