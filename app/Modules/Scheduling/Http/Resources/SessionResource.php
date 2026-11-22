<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Domain\CancellationWindow;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Horários em UTC (contrato estável) e também no fuso do estúdio, para que o
 * cliente não precise saber converter.
 *
 * @property ClassSession $resource
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();
        $timezone = $tenant->timezone;
        $session = $this->resource;

        return [
            'id' => $session->id,
            'series_id' => $session->series_id,
            'title' => $session->title,
            'instructor' => [
                'id' => $session->instructor->id,
                'name' => $session->instructor->name,
            ],
            'starts_at' => $session->starts_at->toIso8601ZuluString(),
            'ends_at' => $session->ends_at->toIso8601ZuluString(),
            'starts_at_local' => $session->starts_at->setTimezone($timezone)->toIso8601String(),
            'ends_at_local' => $session->ends_at->setTimezone($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'cancellation_deadline' => (new CancellationWindow($tenant->cancellation_window_minutes))
                ->deadlineFor($session->starts_at)
                ->toIso8601ZuluString(),
            'capacity' => $session->capacity,
            'confirmed_count' => $session->confirmed_count,
            'available_spots' => max(0, $session->capacity - $session->confirmed_count),
            'waitlist_count' => $session->waitlist_count,
            'canceled_at' => $session->canceled_at?->toIso8601ZuluString(),
            'cancel_reason' => $session->cancel_reason,
        ];
    }
}
