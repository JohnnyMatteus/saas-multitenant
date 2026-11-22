<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Evento `ledger.entry_recorded` v1 do processador de pagamentos, validado.
 * Contrato: event_id, transaction_id, payment_id, direction (credit|debit),
 * amount_cents > 0, currency (3 letras), occurred_at (RFC 3339), schema_version = 1.
 */
final readonly class PaymentNotification
{
    public const int SCHEMA_VERSION = 1;

    public function __construct(
        public string $eventId,
        public string $transactionId,
        public string $paymentId,
        public string $direction,
        public int $amountCents,
        public string $currency,
        public CarbonImmutable $occurredAt,
    ) {}

    /**
     * @throws InvalidPaymentMessage
     */
    public static function fromJson(string $body): self
    {
        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw InvalidPaymentMessage::because('corpo não é um objeto JSON');
        }

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw InvalidPaymentMessage::because('schema_version não suportada');
        }

        foreach (['event_id', 'transaction_id', 'payment_id'] as $field) {
            if (! is_string($data[$field] ?? null) || $data[$field] === '' || strlen($data[$field]) > 128) {
                throw InvalidPaymentMessage::because("{$field} inválido");
            }
        }

        $direction = $data['direction'] ?? null;
        if ($direction !== 'credit' && $direction !== 'debit') {
            throw InvalidPaymentMessage::because('direction inválida');
        }

        $amount = $data['amount_cents'] ?? null;
        if (! is_int($amount) || $amount <= 0) {
            throw InvalidPaymentMessage::because('amount_cents inválido');
        }

        $currency = $data['currency'] ?? null;
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw InvalidPaymentMessage::because('currency inválida');
        }

        $occurredAt = self::rfc3339($data['occurred_at'] ?? null);

        return new self($data['event_id'], $data['transaction_id'], $data['payment_id'], $direction, $amount, $currency, $occurredAt);
    }

    /**
     * RFC 3339 estrito (`2026-11-20T12:00:00Z`, com fração e deslocamento opcionais). Nada de
     * `parse()`: ele aceita vazio, "now" e "tomorrow" e inventaria o instante do pagamento.
     *
     * @throws InvalidPaymentMessage
     */
    private static function rfc3339(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw InvalidPaymentMessage::because('occurred_at inválido');
        }

        // O processador escreve até nanossegundos (Go); o PHP lê até microssegundos.
        $normalized = (string) preg_replace('/(\.\d{6})\d+/', '$1', $value);
        $format = str_contains($normalized, '.') ? 'Y-m-d\TH:i:s.up' : 'Y-m-d\TH:i:sp';
        $parsed = DateTimeImmutable::createFromFormat($format, $normalized);
        $errors = DateTimeImmutable::getLastErrors();

        // Avisos pegam datas que "estouram" em silêncio (30 de fevereiro vira março).
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw InvalidPaymentMessage::because('occurred_at inválido');
        }

        return CarbonImmutable::instance($parsed)->utc();
    }
}
