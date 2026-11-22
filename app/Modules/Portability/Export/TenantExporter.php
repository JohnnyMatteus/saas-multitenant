<?php

declare(strict_types=1);

namespace App\Modules\Portability\Export;

use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSeries;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Exporta os dados de UM estúdio como um documento JSON, escrito aos poucos (não carrega
 * tabelas inteiras na memória).
 *
 * - Tudo roda como o tenant exportado: as consultas passam pelo TenantScope, então não há
 *   como incluir linha de outro estúdio.
 * - As colunas são listadas uma a uma (nada de `toArray()`): coluna nova só sai se alguém a
 *   incluir aqui de propósito.
 * - Uma transação de leitura dá um retrato consistente das tabelas, mesmo com o estúdio em uso.
 * - Fora do export de propósito: vínculo de WhatsApp (código e hash do telefone), o arquivo do logo
 *   e dados de outros estúdios. O corpo traz dado pessoal dos alunos (nome, e-mail).
 */
final readonly class TenantExporter
{
    public const int FORMAT_VERSION = 1;

    private const int CHUNK = 500;

    /** @var array<string, array{0: class-string<Model>, 1: list<string>}> */
    private const array COLLECTIONS = [
        'users' => [User::class, ['id', 'subject', 'email', 'name', 'role']],
        'class_series' => [ClassSeries::class, ['id', 'instructor_id', 'title', 'weekdays', 'start_time', 'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'rolling']],
        'class_sessions' => [ClassSession::class, ['id', 'series_id', 'instructor_id', 'title', 'starts_at', 'ends_at', 'capacity', 'canceled_at', 'cancel_reason']],
        'bookings' => [Booking::class, ['id', 'session_id', 'member_id', 'status', 'queued_at', 'confirmed_at', 'canceled_at']],
        'lesson_packages' => [LessonPackage::class, ['id', 'name', 'lesson_count', 'price_cents', 'currency', 'active']],
        'package_orders' => [PackageOrder::class, ['id', 'package_id', 'member_id', 'payment_id', 'lesson_count', 'amount_cents', 'currency', 'status', 'paid_at']],
        'payment_events' => [PaymentEvent::class, ['id', 'order_id', 'event_id', 'transaction_id', 'direction', 'amount_cents', 'currency', 'occurred_at', 'outcome']],
    ];

    public function __construct(private ConnectionInterface $db, private TenantContext $tenantContext) {}

    /**
     * @param  callable(string): void  $write
     * @return array<string, int> linhas exportadas por coleção
     */
    public function export(Tenant $tenant, callable $write): array
    {
        return $this->tenantContext->runAs($tenant, fn (): array => $this->db->transaction(fn (): array => $this->stream($tenant, $write)));
    }

    /**
     * @param  callable(string): void  $write
     * @return array<string, int>
     */
    private function stream(Tenant $tenant, callable $write): array
    {
        $write('{"format_version":'.self::FORMAT_VERSION.',"exported_at":'.$this->json(now()->toIso8601ZuluString()));
        $write(',"tenant":'.$this->json([
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'cancellation_window_minutes' => $tenant->cancellation_window_minutes,
        ]));

        $counts = [];
        foreach (self::COLLECTIONS as $key => [$model, $columns]) {
            $write(','.$this->json($key).':[');
            $count = 0;
            foreach ($model::query()->lazyById(self::CHUNK) as $row) {
                $write(($count > 0 ? ',' : '').$this->json($row->only($columns)));
                $count++;
            }
            $write(']');
            $counts[$key] = $count;
        }

        $write('}');

        return $counts;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
