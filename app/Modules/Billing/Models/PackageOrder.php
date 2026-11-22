<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de um pacote por um aluno. `payment_id` é a referência enviada ao processador
 * de pagamentos; `lesson_count` e `amount_cents` são a cópia do pacote na hora da compra.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $package_id
 * @property string $member_id
 * @property string $payment_id
 * @property int $lesson_count
 * @property int $amount_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 * @property-read LessonPackage $package
 * @property-read User $member
 */
class PackageOrder extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['tenant_id', 'package_id', 'member_id', 'payment_id', 'lesson_count', 'amount_cents', 'currency', 'status', 'paid_at'];

    protected function casts(): array
    {
        return [
            'lesson_count' => 'integer',
            'amount_cents' => 'integer',
            'status' => OrderStatus::class,
            'paid_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<LessonPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'package_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
