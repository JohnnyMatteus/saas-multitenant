<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\Exceptions\InvalidPaymentMessage;
use App\Modules\Billing\Domain\PaymentNotification;

/**
 * Corpo bruto da mensagem -> evento validado -> caso de uso.
 * `InvalidPaymentMessage` é erro permanente (DLQ); qualquer outra exceção é transitória (reentrega).
 */
final readonly class HandlePaymentMessage
{
    public function __construct(private RecordPaymentNotification $record) {}

    /**
     * @throws InvalidPaymentMessage
     */
    public function __invoke(string $body): void
    {
        $this->record->handle(PaymentNotification::fromJson($body));
    }
}
