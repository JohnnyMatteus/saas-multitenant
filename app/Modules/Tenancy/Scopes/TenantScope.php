<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Scopes;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra toda consulta pelo tenant corrente.
 *
 * O contexto é resolvido a cada consulta (e não no boot do modelo) para que
 * workers de longa duração nunca reaproveitem o tenant de outra requisição.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class)->current();

        $builder->where($model->qualifyColumn('tenant_id'), $tenant->getKey());
    }
}
