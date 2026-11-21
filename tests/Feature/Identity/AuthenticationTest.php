<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\JwtFactory;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
    }

    #[Test]
    public function a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'unauthenticated');
    }

    #[Test]
    public function an_invalid_token_gets_a_generic_401(): void
    {
        $token = JwtFactory::token(['sub' => 'x', 'tenant' => 'estudio-lotus', 'exp' => now()->getTimestamp() - 3600]);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('detail', 'Token inválido.');
    }

    #[Test]
    public function an_expired_token_is_rejected_after_time_passes(): void
    {
        $user = $this->createUser($this->lotus, Role::Student);
        $token = JwtFactory::forUser($user, $this->lotus);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->travel(10)->minutes();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    #[Test]
    public function a_token_without_a_studio_role_is_forbidden(): void
    {
        $token = JwtFactory::token([
            'sub' => 'x', 'tenant' => 'estudio-lotus', 'realm_access' => ['roles' => ['offline_access']],
        ]);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('type', 'role_not_recognized');

        $this->assertSame(0, User::withoutTenancy()->count());
    }

    #[Test]
    public function the_first_request_provisions_the_user_just_in_time(): void
    {
        $token = JwtFactory::token([
            'sub' => 'kc-123', 'tenant' => 'estudio-lotus', 'email' => 'nova.aluna@example.com',
            'name' => 'Nova Aluna Exemplo', 'realm_access' => ['roles' => ['student']],
        ]);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Nova Aluna Exemplo')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.studio.timezone', 'America/Sao_Paulo');

        $user = User::withoutTenancy()->where('subject', 'kc-123')->sole();
        $this->assertSame($this->lotus->id, $user->tenant_id);
        $this->assertSame('nova.aluna@example.com', $user->email);
    }

    #[Test]
    public function role_changes_in_the_identity_provider_are_applied_on_the_next_request(): void
    {
        $user = $this->createUser($this->lotus, Role::Student);

        $promoted = JwtFactory::forUser($user, $this->lotus, ['realm_access' => ['roles' => ['instructor']]]);
        $this->withToken($promoted)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.role', 'instructor');

        $this->assertSame(Role::Instructor, User::withoutTenancy()->findOrFail($user->id)->role);
        $this->assertSame(1, User::withoutTenancy()->count());
    }

    #[Test]
    public function no_identity_or_tenant_leaks_into_the_next_request(): void
    {
        $user = $this->createUser($this->lotus, Role::Student);

        $this->actingAsJwt($user)->getJson('/api/v1/me')->assertOk();

        $this->assertNull($this->app->make(TenantContext::class)->currentOrNull());
        $this->withoutToken()->getJson('/api/v1/me')->assertUnauthorized();
    }
}
