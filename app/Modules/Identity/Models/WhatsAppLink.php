<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in do aluno para ser atendido pelo agente de WhatsApp do estúdio.
 *
 * Nasce com um código pendente (gerado logado no SaaS) e vira vínculo quando
 * o agente confirma esse código a partir do telefone do aluno.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string|null $contact_hash
 * @property string|null $code_hash
 * @property CarbonImmutable|null $code_expires_at
 * @property CarbonImmutable|null $linked_at
 * @property-read User $user
 */
class WhatsAppLink extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $table = 'whatsapp_links';

    protected $fillable = ['tenant_id', 'user_id', 'contact_hash', 'code_hash', 'code_expires_at', 'linked_at'];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'immutable_datetime',
            'linked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLinked(): bool
    {
        return $this->contact_hash !== null && $this->linked_at !== null;
    }

    public function hasPendingCode(CarbonImmutable $now): bool
    {
        return $this->code_hash !== null && $this->code_expires_at !== null && $this->code_expires_at->isAfter($now);
    }
}
