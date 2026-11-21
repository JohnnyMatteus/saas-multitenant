<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class WhatsAppAgentRequired extends DomainError
{
    public function __construct()
    {
        parent::__construct('Rota restrita à conta de serviço do agente de WhatsApp.');
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return 'whatsapp_agent_required';
    }
}
