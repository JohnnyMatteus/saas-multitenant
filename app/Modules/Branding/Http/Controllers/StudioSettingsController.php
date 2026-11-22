<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Controllers;

use App\Modules\Branding\Http\Requests\UpdateStudioSettingsRequest;
use App\Modules\Branding\Http\Resources\StudioSettingsResource;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Configurações do estúdio do token. O fuso não é editável: as ocorrências já
 * materializadas foram geradas nele (ADR 0005).
 */
final class StudioSettingsController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function show(): StudioSettingsResource
    {
        return new StudioSettingsResource($this->tenantContext->current());
    }

    public function update(UpdateStudioSettingsRequest $request): StudioSettingsResource
    {
        Gate::authorize('manage-studio-branding');

        $tenant = $this->tenantContext->current();
        $tenant->update($request->validated());

        return new StudioSettingsResource($tenant);
    }
}
