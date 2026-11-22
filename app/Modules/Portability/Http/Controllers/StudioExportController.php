<?php

declare(strict_types=1);

namespace App\Modules\Portability\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Observability\Metrics;
use App\Modules\Portability\Export\TenantExporter;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StudioExportController
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantExporter $exporter,
        private Metrics $metrics,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(#[CurrentUser] User $user): StreamedResponse
    {
        Gate::authorize('export-studio-data');

        // O corpo é gerado depois que o middleware de autenticação já limpou o contexto:
        // o tenant e o autor são capturados agora e o exportador roda como esse tenant.
        $tenant = $this->tenantContext->current();
        $userId = $user->id;
        $filename = sprintf('%s-%s.json', $tenant->slug, now()->format('Ymd-His'));

        return new StreamedResponse(function () use ($tenant, $userId): void {
            $counts = $this->exporter->export($tenant, function (string $chunk): void {
                echo $chunk;
                flush();
            });

            // Auditoria: quem exportou e quanto, sem dado pessoal.
            $this->logger->notice('studio_export', ['tenant_id' => $tenant->id, 'user_id' => $userId, 'rows' => $counts]);
            $this->metrics->increment('studio_exports_total', 'Exportações de dados do estúdio concluídas.');
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
