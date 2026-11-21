<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Application\CancelClassSession;
use App\Modules\Scheduling\Application\EditClassSeries;
use App\Modules\Scheduling\Http\Requests\CancelSessionRequest;
use App\Modules\Scheduling\Http\Requests\UpdateSessionRequest;
use App\Modules\Scheduling\Http\Resources\SessionResource;
use App\Modules\Scheduling\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Operações do admin sobre aulas: editar (com propagação na série) e cancelar.
 */
final class SessionManagementController
{
    public function update(string $session, UpdateSessionRequest $request, EditClassSeries $editClassSeries): JsonResponse
    {
        $model = ClassSession::query()->findOrFail($session);
        Gate::authorize('manage', $model);

        $result = $editClassSeries->handle($model, $request->toData(), $request->scope());

        return new JsonResponse(['data' => [
            'series_id' => $result->seriesId,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
        ]]);
    }

    public function cancel(string $session, CancelSessionRequest $request, CancelClassSession $cancelClassSession): SessionResource
    {
        $model = ClassSession::query()->findOrFail($session);
        Gate::authorize('manage', $model);

        $canceled = $cancelClassSession->handle($model, $request->reason());

        return new SessionResource(
            ClassSession::query()->with('instructor')->withSeatCounts()->findOrFail($canceled->id),
        );
    }
}
