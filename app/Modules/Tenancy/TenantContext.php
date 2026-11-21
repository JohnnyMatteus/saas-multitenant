<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Exceptions\TenantContextMissing;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Tenant da requisição (ou do job) em execução.
 *
 * Registrado como "scoped": o container descarta a instância ao fim de cada
 * requisição/job, inclusive em workers de longa duração. Sem tenant definido,
 * qualquer leitura de modelo multitenant falha (fail-closed).
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function current(): Tenant
    {
        return $this->tenant ?? throw new TenantContextMissing;
    }

    public function currentOrNull(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Executa o callback como o tenant informado e restaura o anterior.
     * Usado por seeders, comandos e jobs, que não passam pelo middleware HTTP.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
