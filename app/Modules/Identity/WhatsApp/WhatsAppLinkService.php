<?php

declare(strict_types=1);

namespace App\Modules\Identity\WhatsApp;

use App\Modules\Identity\Exceptions\InvalidLinkCode;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\WhatsAppLink;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo entre o telefone do WhatsApp e o aluno, dentro do estúdio do contexto.
 *
 * O código prova a posse da conta do SaaS; a mensagem assinada pela Meta, a posse
 * do telefone. Só os dois juntos criam o vínculo.
 */
final readonly class WhatsAppLinkService
{
    private const int CODE_TTL_MINUTES = 10;

    private const int MAX_CODE_ATTEMPTS = 5;

    public function __construct(private ContactHasher $hasher, private TenantContext $tenantContext) {}

    public function issueCode(User $student): IssuedCode
    {
        $tenantId = $this->tenantContext->current()->id;
        $expiresAt = CarbonImmutable::now()->addMinutes(self::CODE_TTL_MINUTES);

        for ($attempt = 1; ; $attempt++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

            try {
                // Código pendente de outro aluno no estúdio viola a unique: sorteia outro.
                WhatsAppLink::query()->updateOrCreate(
                    ['user_id' => $student->id],
                    [
                        'tenant_id' => $tenantId,
                        'code_hash' => $this->hasher->code($tenantId, $code),
                        'code_expires_at' => $expiresAt,
                    ],
                );

                return new IssuedCode($code, $expiresAt);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_CODE_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    public function statusOf(User $student): ?WhatsAppLink
    {
        return WhatsAppLink::query()->where('user_id', $student->id)->first();
    }

    public function revoke(User $student): void
    {
        WhatsAppLink::query()->where('user_id', $student->id)->delete();
    }

    public function confirm(string $code, string $contact): WhatsAppLink
    {
        $tenantId = $this->tenantContext->current()->id;
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($tenantId, $code, $contact, $now): WhatsAppLink {
            $link = WhatsAppLink::query()
                ->where('code_hash', $this->hasher->code($tenantId, $code))
                ->where('code_expires_at', '>', $now)
                ->lockForUpdate()
                ->first() ?? throw new InvalidLinkCode;

            $contactHash = $this->hasher->contact($tenantId, $contact);

            // O telefone muda de dono para quem provou o código agora.
            WhatsAppLink::query()
                ->where('contact_hash', $contactHash)
                ->whereKeyNot($link->id)
                ->update(['contact_hash' => null, 'linked_at' => null]);

            $link->forceFill([
                'contact_hash' => $contactHash,
                'linked_at' => $now,
                'code_hash' => null,
                'code_expires_at' => null,
            ])->save();

            return $link->load('user');
        });
    }

    public function studentForContact(string $contact): ?User
    {
        $link = WhatsAppLink::query()
            ->with('user')
            ->where('contact_hash', $this->hasher->contact($this->tenantContext->current()->id, $contact))
            ->whereNotNull('linked_at')
            ->first();

        return $link?->user?->hasRole(Role::Student) === true ? $link->user : null;
    }
}
