<?php

namespace Tests;

use App\Modules\Identity\Jwt\KeySetProvider;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\JwtFactory;

abstract class TestCase extends BaseTestCase
{
    private static ?string $publicKeyPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercita o modo "local" real: chave pública lida de um arquivo PEM.
        config([
            'jwt.local.kid' => JwtFactory::KID,
            'jwt.local.public_key_path' => self::publicKeyPath(),
        ]);
        $this->app->forgetInstance(KeySetProvider::class);
    }

    protected function createTenant(string $slug, string $timezone = 'America/Sao_Paulo', int $cancellationWindowMinutes = 720): Tenant
    {
        return Tenant::factory()->create([
            'slug' => $slug,
            'name' => 'Estúdio '.$slug.' Exemplo',
            'timezone' => $timezone,
            'cancellation_window_minutes' => $cancellationWindowMinutes,
        ]);
    }

    protected function createUser(Tenant $tenant, Role $role, string $name = 'Pessoa Exemplo'): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $tenant->id, 'name' => $name]);
    }

    /**
     * Próximas requisições autenticadas com um JWT válido do usuário.
     */
    protected function actingAsJwt(User $user, ?Tenant $tenant = null): static
    {
        $tenant ??= Tenant::query()->findOrFail($user->tenant_id);

        return $this->withToken(JwtFactory::forUser($user, $tenant));
    }

    private static function publicKeyPath(): string
    {
        if (self::$publicKeyPath === null) {
            $path = tempnam(sys_get_temp_dir(), 'jwt-test-pub-');
            file_put_contents((string) $path, JwtFactory::publicKey());
            self::$publicKeyPath = (string) $path;
        }

        return self::$publicKeyPath;
    }
}
