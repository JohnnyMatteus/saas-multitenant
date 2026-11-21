<?php

declare(strict_types=1);

namespace App\Modules\Branding;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

final class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoStorage::class, fn (Application $app): LogoStorage => new LogoStorage(
            Storage::disk((string) $app->make('config')->get('studio_media.disk')),
        ));
    }

    public function boot(): void
    {
        Gate::define('manage-studio-branding', fn (User $user): bool => $user->hasRole(Role::StudioAdmin));
    }
}
