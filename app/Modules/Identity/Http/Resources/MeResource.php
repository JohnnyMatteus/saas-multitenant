<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property User $resource
 */
final class MeResource extends JsonResource
{
    public function __construct(User $user, private readonly Tenant $tenant)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'studio' => [
                'id' => $this->tenant->id,
                'slug' => $this->tenant->slug,
                'name' => $this->tenant->name,
                'timezone' => $this->tenant->timezone,
            ],
        ];
    }
}
