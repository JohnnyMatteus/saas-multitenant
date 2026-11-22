<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\Exceptions\PackageUnavailable;
use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Cria o pedido de um pacote e a referência de pagamento (`payment_id`) que o aluno leva ao
 * processador. O pacote é copiado para o pedido: mudar o catálogo depois não o altera.
 * Não concede nada: só um evento de pagamento confirmado muda o estado do pedido.
 */
final class CreatePackageOrder
{
    public function handle(User $member, string $packageId): PackageOrder
    {
        $package = LessonPackage::query()->findOrFail($packageId);
        if (! $package->active) {
            throw new PackageUnavailable;
        }

        return PackageOrder::query()->create([
            'package_id' => $package->id,
            'member_id' => $member->id,
            'payment_id' => 'pay_'.Str::lower((string) Str::ulid()),
            'lesson_count' => $package->lesson_count,
            'amount_cents' => $package->price_cents,
            'currency' => $package->currency,
            'status' => OrderStatus::Pending,
        ]);
    }
}
