<?php

declare(strict_types=1);

namespace App\Modules\Billing\Payments;

/**
 * Recebe, por callback, o motivo de o broker ter recusado ou não roteado uma publicação.
 */
final class BrokerRefusal
{
    private ?string $reason = null;

    public function record(string $reason): void
    {
        $this->reason = $reason;
    }

    public function clear(): void
    {
        $this->reason = null;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
