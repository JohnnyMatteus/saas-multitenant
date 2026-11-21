<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Console;

use App\Modules\Scheduling\Jobs\RunSchedulingMaintenance;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Um job por estúdio ativo: falha ou lentidão de um tenant não atrasa os outros.
 */
final class DispatchSchedulingMaintenance extends Command
{
    protected $signature = 'scheduling:maintain {--horizon : só estende o horizonte das séries} {--reminders : só envia lembretes}';

    protected $description = 'Estende o horizonte das séries e despacha lembretes, um job por estúdio.';

    public function handle(): int
    {
        $onlyHorizon = (bool) $this->option('horizon');
        $onlyReminders = (bool) $this->option('reminders');
        $both = ! $onlyHorizon && ! $onlyReminders;
        $count = 0;

        foreach (Tenant::query()->where('active', true)->pluck('id') as $tenantId) {
            RunSchedulingMaintenance::dispatch($tenantId, $both || $onlyHorizon, $both || $onlyReminders);
            $count++;
        }

        $this->info("{$count} estúdio(s) despachado(s).");

        return self::SUCCESS;
    }
}
