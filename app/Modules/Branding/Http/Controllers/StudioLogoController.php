<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Controllers;

use App\Modules\Branding\Http\Requests\UploadLogoRequest;
use App\Modules\Branding\LogoStorage;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Logo do estúdio (white label). Sempre o logo do tenant do token: não há
 * id na rota, logo não há o que adivinhar.
 */
final class StudioLogoController
{
    public function __construct(
        private readonly LogoStorage $logos,
        private readonly TenantContext $tenantContext,
    ) {}

    public function show(): StreamedResponse
    {
        return $this->logos->response($this->tenantContext->current())
            ?? throw new NotFoundHttpException;
    }

    public function store(UploadLogoRequest $request): JsonResponse
    {
        Gate::authorize('manage-studio-branding');

        $tenant = $this->tenantContext->current();
        $tenant->update(['logo_path' => $this->logos->store($tenant, $request->file('logo'))]);

        return new JsonResponse(['data' => ['logo_path' => $tenant->logo_path]]);
    }
}
