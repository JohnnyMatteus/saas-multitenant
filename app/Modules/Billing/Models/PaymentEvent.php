<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Domain\PaymentOutcome;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento do processador de pagamentos já tratado. A unique em `event_id` é a idempotência.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $event_id
 * @property string $transaction_id
 * @property string $direction
 * @property int $amount_cents
 * @property string $currency
 * @property CarbonImmutable $occurred_at
 * @property PaymentOutcome $outcome
 */
class PaymentEvent extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['tenant_id', 'order_id', 'event_id', 'transaction_id', 'direction', 'amount_cents', 'currency', 'occurred_at', 'outcome'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'occurred_at' => 'immutable_datetime', 'outcome' => PaymentOutcome::class];
    }
}
