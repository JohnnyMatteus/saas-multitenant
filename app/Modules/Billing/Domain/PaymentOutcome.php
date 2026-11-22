<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

/**
 * O que o evento causou. Estorno (`debit`) só é registrado: o efeito dele nos créditos
 * depende de política que o autor ainda não definiu.
 */
enum PaymentOutcome: string
{
    case Paid = 'paid';
    case AlreadyPaid = 'already_paid';
    case AmountMismatch = 'amount_mismatch';
    case RefundRecorded = 'refund_recorded';

    // Resultados que não geram linha em payment_events.
    case Duplicate = 'duplicate';
    case UnknownPayment = 'unknown_payment';
}
