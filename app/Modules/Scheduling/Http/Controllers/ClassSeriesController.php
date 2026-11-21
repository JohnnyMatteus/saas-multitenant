<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Application\CreateClassSeries;
use App\Modules\Scheduling\Http\Requests\CreateClassSeriesRequest;
use App\Modules\Scheduling\Http\Resources\ClassSeriesResource;
use App\Modules\Scheduling\Models\ClassSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ClassSeriesController
{
    public function store(CreateClassSeriesRequest $request, CreateClassSeries $createClassSeries): JsonResponse
    {
        Gate::authorize('create', ClassSeries::class);

        $series = $createClassSeries->handle($request->toData());

        return (new ClassSeriesResource($series))->response()->setStatusCode(201);
    }
}
