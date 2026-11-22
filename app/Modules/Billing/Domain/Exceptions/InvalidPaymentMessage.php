<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use RuntimeException;

/**
 * Mensagem fora do contrato: erro permanente, reenviar não adianta (vai para a DLQ).
 */
final class InvalidPaymentMessage extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Mensagem de pagamento inválida: {$reason}.");
    }
}
