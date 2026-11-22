<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\PackageOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PackageOrder $resource
 */
final class PackageOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'id' => $order->id,
            'payment_id' => $order->payment_id,
            'package' => ['id' => $order->package_id, 'name' => $order->package->name],
            'member' => ['id' => $order->member_id, 'name' => $order->member->name],
            'lesson_count' => $order->lesson_count,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => $order->status->value,
            'paid_at' => $order->paid_at?->toIso8601ZuluString(),
            'created_at' => $order->created_at->toIso8601ZuluString(),
        ];
    }
}
