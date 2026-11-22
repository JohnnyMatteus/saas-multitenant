<?php

declare(strict_types=1);

namespace App\Modules\Identity\WhatsApp;

/**
 * Contrato com a conta de serviço do agente de WhatsApp de cada estúdio.
 */
final class WhatsAppAgent
{
    /** Realm role do Keycloak; não é um papel do estúdio e não dá acesso ao domínio. */
    public const string ROLE = 'whatsapp_agent';

    /** Telefone do remetente (wa_id) que o agente quer representar. */
    public const string CONTACT_HEADER = 'X-WhatsApp-Contact';

    public const string CONTACT_PATTERN = '/^\d{8,20}$/';
}
