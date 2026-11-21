<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Identity\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StudioSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus', 'America/Sao_Paulo', 720);
        $this->equilibrio = $this->createTenant('pilates-equilibrio', 'America/Manaus', 240);
    }

    #[Test]
    public function anyone_in_the_studio_reads_its_settings(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->getJson('/api/v1/studio')
            ->assertOk()
            ->assertJsonPath('data.slug', 'estudio-lotus')
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.cancellation_window_minutes', 720)
            ->assertJsonPath('data.has_logo', false);
    }

    #[Test]
    public function the_admin_updates_name_and_cancellation_window(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::StudioAdmin))
            ->patchJson('/api/v1/studio', ['name' => 'Estúdio Lótus Centro Exemplo', 'cancellation_window_minutes' => 180])
            ->assertOk()
            ->assertJsonPath('data.name', 'Estúdio Lótus Centro Exemplo')
            ->assertJsonPath('data.cancellation_window_minutes', 180);

        $this->assertSame(180, $this->lotus->refresh()->cancellation_window_minutes);
        $this->assertSame(240, $this->equilibrio->refresh()->cancellation_window_minutes);
    }

    #[Test]
    public function the_timezone_is_not_editable(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::StudioAdmin))
            ->patchJson('/api/v1/studio', ['timezone' => 'Europe/Lisbon'])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo');
    }

    #[Test]
    public function invalid_values_are_rejected(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::StudioAdmin))
            ->patchJson('/api/v1/studio', ['cancellation_window_minutes' => -5, 'name' => 'x'])
            ->assertUnprocessable();
    }

    #[Test]
    public function only_the_admin_changes_settings(): void
    {
        foreach ([Role::Student, Role::Instructor] as $role) {
            $this->actingAsJwt($this->createUser($this->lotus, $role))
                ->patchJson('/api/v1/studio', ['cancellation_window_minutes' => 0])
                ->assertForbidden();
        }

        $this->assertSame(720, $this->lotus->refresh()->cancellation_window_minutes);
    }
}
