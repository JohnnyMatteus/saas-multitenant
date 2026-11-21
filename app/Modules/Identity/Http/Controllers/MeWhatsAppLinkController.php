<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\WhatsApp\WhatsAppLinkService;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final readonly class MeWhatsAppLinkController
{
    public function __construct(private WhatsAppLinkService $links) {}

    public function issueCode(#[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('manage-whatsapp-link');

        $issued = $this->links->issueCode($user);

        return new JsonResponse(
            ['data' => ['code' => $issued->code, 'expires_at' => $issued->expiresAt->toIso8601ZuluString()]],
            201,
        );
    }

    public function show(#[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('manage-whatsapp-link');

        $link = $this->links->statusOf($user);

        return new JsonResponse(['data' => [
            'linked' => $link?->isLinked() ?? false,
            'linked_at' => $link?->linked_at?->toIso8601ZuluString(),
            'code_pending' => $link?->hasPendingCode(CarbonImmutable::now()) ?? false,
        ]]);
    }

    public function destroy(#[CurrentUser] User $user): Response
    {
        Gate::authorize('manage-whatsapp-link');

        $this->links->revoke($user);

        return new Response(status: 204);
    }
}
