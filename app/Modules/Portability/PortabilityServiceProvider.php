<?php

declare(strict_types=1);

namespace App\Modules\Portability;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class PortabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('export-studio-data', fn (User $user): bool => $user->hasRole(Role::StudioAdmin));

        // O export lê o estúdio inteiro: poucas por hora, por estúdio e usuário (quem não é admin
        // também gasta o limite do próprio usuário, nunca o do admin).
        RateLimiter::for('studio-export', function (Request $request): Limit {
            $tenant = $this->app->make(TenantContext::class)->current()->id;

            return Limit::perHour(5)->by($tenant.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
