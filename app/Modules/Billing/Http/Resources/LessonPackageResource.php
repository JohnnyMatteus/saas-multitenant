<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\LessonPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property LessonPackage $resource
 */
final class LessonPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'lesson_count' => $this->resource->lesson_count,
            'price_cents' => $this->resource->price_cents,
            'currency' => $this->resource->currency,
            'active' => $this->resource->active,
        ];
    }
}
