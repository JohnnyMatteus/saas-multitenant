<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Exceptions\CrossTenantWrite;
use App\Modules\Tenancy\Exceptions\TenantContextMissing;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca um modelo como pertencente a um tenant.
 *
 * - Leitura: global scope por tenant_id; sem tenant no contexto, falha.
 * - Criação: preenche tenant_id com o tenant corrente; recusa tenant diferente.
 * - Atualização: tenant_id é imutável.
 *
 * Criar com tenant_id explícito e sem contexto é permitido apenas para código
 * de sistema (seeders e factories), que não atende requisições de usuário.
 *
 * @property string $tenant_id
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = app(TenantContext::class)->currentOrNull();
            $tenantId = $model->getAttribute('tenant_id');

            if ($tenantId === null) {
                $model->setAttribute('tenant_id', $current?->getKey() ?? throw new TenantContextMissing);

                return;
            }

            if ($current !== null && $tenantId !== $current->getKey()) {
                throw CrossTenantWrite::onCreate($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw CrossTenantWrite::onUpdate($model::class);
            }
        });
    }

    /**
     * Consulta sem o filtro de tenant. Uso restrito a código de sistema e
     * testes; cada chamada deve ser justificável em revisão.
     *
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
