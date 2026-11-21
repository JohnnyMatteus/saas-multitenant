<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Resources;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Tenant $resource
 */
final class StudioSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource;

        return [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'cancellation_window_minutes' => $tenant->cancellation_window_minutes,
            'has_logo' => $tenant->logo_path !== null,
        ];
    }
}
