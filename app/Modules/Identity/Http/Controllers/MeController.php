<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MeController
{
    public function __invoke(#[CurrentUser] User $user, TenantContext $tenantContext): JsonResponse
    {
        // Status fixo: o provisionamento JIT no primeiro acesso não é um "201" para o cliente.
        return (new MeResource($user, $tenantContext->current()))->response()->setStatusCode(200);
    }
}
