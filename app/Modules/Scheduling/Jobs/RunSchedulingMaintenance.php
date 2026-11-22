<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Jobs;

use App\Modules\Scheduling\Application\ExtendSeriesHorizon;
use App\Modules\Scheduling\Application\SendBookingReminders;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Manutenção periódica de um estúdio, na fila Redis: estende o horizonte das
 * séries e despacha lembretes. O tenant vem no payload (não há requisição).
 */
final class RunSchedulingMaintenance implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly bool $extendHorizon = true,
        public readonly bool $reminders = true,
    ) {}

    public function handle(TenantContext $tenantContext, ExtendSeriesHorizon $extend, SendBookingReminders $remind): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null || ! $tenant->active) {
            return;
        }

        $tenantContext->runAs($tenant, function () use ($extend, $remind): void {
            if ($this->extendHorizon) {
                $extend->handle();
            }

            if ($this->reminders) {
                $remind->handle();
            }
        });
    }
}
