<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\LessonPackageRequest;
use App\Modules\Billing\Http\Resources\LessonPackageResource;
use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class LessonPackageController
{
    /** Admin vê o catálogo todo; os demais, só o que está à venda. */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $packages = LessonPackage::query()
            ->when(! $user->hasRole(Role::StudioAdmin), fn ($q) => $q->where('active', true))
            ->orderBy('price_cents')
            ->orderBy('id')
            ->get();

        return LessonPackageResource::collection($packages);
    }

    public function store(LessonPackageRequest $request): JsonResponse
    {
        Gate::authorize('manage-lesson-packages');

        // `active` explícito: o default do banco não volta para o modelo recém-criado.
        $package = LessonPackage::query()->create([...$request->safe()->only(['name', 'lesson_count', 'price_cents']), 'currency' => 'BRL', 'active' => true]);

        return (new LessonPackageResource($package))->response()->setStatusCode(201);
    }

    public function update(string $package, LessonPackageRequest $request): LessonPackageResource
    {
        Gate::authorize('manage-lesson-packages');

        $model = LessonPackage::query()->findOrFail($package);
        $model->update($request->safe()->only(['name', 'price_cents', 'active']));

        return new LessonPackageResource($model);
    }
}
