<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Http\Requests\ListSessionsRequest;
use App\Modules\Scheduling\Http\Resources\SessionResource;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Grade de aulas, visível a qualquer papel do estúdio.
 */
final class SessionController
{
    public function index(ListSessionsRequest $request, TenantContext $tenantContext): AnonymousResourceCollection
    {
        [$from, $to] = $request->utcRange($tenantContext->current()->timezone);

        $sessions = ClassSession::query()
            ->with('instructor')
            ->withSeatCounts()
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        return SessionResource::collection($sessions);
    }

    public function show(string $session): SessionResource
    {
        return new SessionResource(
            ClassSession::query()->with('instructor')->withSeatCounts()->findOrFail($session),
        );
    }
}
