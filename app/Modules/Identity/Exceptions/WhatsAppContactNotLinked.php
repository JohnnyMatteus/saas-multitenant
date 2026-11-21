<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\DomainError;

final class WhatsAppContactNotLinked extends DomainError
{
    public function __construct()
    {
        parent::__construct('Este telefone não está vinculado a um aluno do estúdio.');
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return 'whatsapp_contact_not_linked';
    }
}
