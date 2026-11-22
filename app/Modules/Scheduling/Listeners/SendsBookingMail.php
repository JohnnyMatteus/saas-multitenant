<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Base dos e-mails de reserva: roda na fila (Redis no docker-compose), fora
 * da transação do fato gerador, restaurando o tenant que veio no evento.
 * Falha de SMTP nunca desfaz a reserva.
 */
abstract class SendsBookingMail implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        protected readonly TenantContext $tenantContext,
        protected readonly Mailer $mailer,
    ) {}

    /**
     * Executa $build como o tenant e envia o que ele devolver; null = não enviar.
     *
     * @param  callable(Tenant): (array{0: User, 1: Mailable}|null)  $build
     */
    protected function deliver(string $tenantId, callable $build): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        $this->tenantContext->runAs($tenant, function () use ($tenant, $build): void {
            $message = $build($tenant);

            if ($message === null || $message[0]->email === null) {
                return;
            }

            $this->mailer->to($message[0]->email)->send($message[1]);
        });
    }

    protected function localStart(ClassSession $session, Tenant $tenant): string
    {
        return $session->starts_at->setTimezone($tenant->timezone)->format('d/m/Y \à\s H:i');
    }
}
