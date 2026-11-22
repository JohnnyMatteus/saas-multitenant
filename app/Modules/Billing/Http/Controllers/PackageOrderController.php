<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Application\CreatePackageOrder;
use App\Modules\Billing\Http\Requests\CreatePackageOrderRequest;
use App\Modules\Billing\Http\Resources\PackageOrderResource;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PackageOrderController
{
    public function store(CreatePackageOrderRequest $request, #[CurrentUser] User $user, CreatePackageOrder $create): JsonResponse
    {
        Gate::authorize('purchase-lesson-packages');

        $order = $create->handle($user, $request->string('package_id')->toString())->load(['package', 'member']);

        return (new PackageOrderResource($order))->response()->setStatusCode(201);
    }

    public function mine(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return PackageOrderResource::collection(
            PackageOrder::query()->with(['package', 'member'])->where('member_id', $user->id)->latest('created_at')->latest('id')->paginate(50),
        );
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-lesson-packages');

        return PackageOrderResource::collection(
            PackageOrder::query()->with(['package', 'member'])->latest('created_at')->latest('id')->paginate(50),
        );
    }
}
