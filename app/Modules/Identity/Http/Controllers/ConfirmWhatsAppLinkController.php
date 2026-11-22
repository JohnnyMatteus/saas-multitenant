<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\WhatsApp\WhatsAppAgent;
use App\Modules\Identity\WhatsApp\WhatsAppLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ConfirmWhatsAppLinkController
{
    public function __construct(private WhatsAppLinkService $links) {}

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'contact' => ['required', 'string', 'regex:'.WhatsAppAgent::CONTACT_PATTERN],
        ]);

        $link = $this->links->confirm($input['code'], $input['contact']);

        return new JsonResponse(['data' => [
            'member_name' => $link->user->name,
            'linked_at' => $link->linked_at?->toIso8601ZuluString(),
        ]], 201);
    }
}
