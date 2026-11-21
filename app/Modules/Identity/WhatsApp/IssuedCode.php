<?php

declare(strict_types=1);

namespace App\Modules\Identity\WhatsApp;

use Carbon\CarbonImmutable;

final readonly class IssuedCode
{
    public function __construct(public string $code, public CarbonImmutable $expiresAt) {}
}
