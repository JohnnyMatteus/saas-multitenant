<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LessonPackageCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    private User $admin;

    private User $student;

    private User $instructor;

    private User $equilibrioAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
        $this->admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $this->student = $this->createUser($this->lotus, Role::Student);
        $this->instructor = $this->createUser($this->lotus, Role::Instructor);
        $this->equilibrioAdmin = $this->createUser($this->equilibrio, Role::StudioAdmin);
    }

    private function package(Tenant $tenant, string $name, int $priceCents = 10000, bool $active = true): LessonPackage
    {
        return LessonPackage::query()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'lesson_count' => 8, 'price_cents' => $priceCents, 'currency' => 'BRL', 'active' => $active,
        ]);
    }

    #[Test]
    public function only_the_admin_creates_packages_and_the_currency_is_brl(): void
    {
        $payload = ['name' => 'Pacote 8 aulas', 'lesson_count' => 8, 'price_cents' => 32000];

        $this->actingAsJwt($this->student)->postJson('/api/v1/lesson-packages', $payload)->assertForbidden();
        $this->actingAsJwt($this->instructor)->postJson('/api/v1/lesson-packages', $payload)->assertForbidden();

        $this->actingAsJwt($this->admin)->postJson('/api/v1/lesson-packages', $payload + ['currency' => 'USD'])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'BRL')
            ->assertJsonPath('data.price_cents', 32000)
            ->assertJsonPath('data.active', true);
        $this->assertSame($this->lotus->id, LessonPackage::withoutTenancy()->sole()->tenant_id);
    }

    #[Test]
    public function the_request_is_validated(): void
    {
        $this->actingAsJwt($this->admin)->postJson('/api/v1/lesson-packages', ['name' => 'x', 'lesson_count' => 0, 'price_cents' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lesson_count', 'price_cents']);
        $this->assertSame(0, LessonPackage::withoutTenancy()->count());
    }

    #[Test]
    public function students_see_only_active_packages_and_the_admin_sees_all(): void
    {
        $this->package($this->lotus, 'Ativo');
        $this->package($this->lotus, 'Suspenso', active: false);

        $this->actingAsJwt($this->student)->getJson('/api/v1/lesson-packages')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ativo');
        $this->actingAsJwt($this->admin)->getJson('/api/v1/lesson-packages')->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function the_catalog_never_includes_another_studios_packages(): void
    {
        $this->package($this->equilibrio, 'Pacote do outro estúdio');

        $this->actingAsJwt($this->admin)->getJson('/api/v1/lesson-packages')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAsJwt($this->student)->getJson('/api/v1/lesson-packages')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAsJwt($this->equilibrioAdmin)->getJson('/api/v1/lesson-packages')->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function editing_another_studios_package_returns_not_found_and_changes_nothing(): void
    {
        $foreign = $this->package($this->equilibrio, 'Pacote do outro estúdio', 9000);

        $this->actingAsJwt($this->admin)->patchJson("/api/v1/lesson-packages/{$foreign->id}", ['price_cents' => 1, 'active' => false])
            ->assertNotFound();

        $fresh = LessonPackage::withoutTenancy()->findOrFail($foreign->id);
        $this->assertSame(9000, $fresh->price_cents);
        $this->assertTrue($fresh->active);
    }

    #[Test]
    public function the_admin_edits_price_and_availability_but_not_the_lesson_count(): void
    {
        $package = $this->package($this->lotus, 'Pacote');

        $this->actingAsJwt($this->student)->patchJson("/api/v1/lesson-packages/{$package->id}", ['active' => false])->assertForbidden();
        $this->actingAsJwt($this->admin)->patchJson("/api/v1/lesson-packages/{$package->id}", ['lesson_count' => 99])->assertUnprocessable();
        $this->actingAsJwt($this->admin)->patchJson("/api/v1/lesson-packages/{$package->id}", ['price_cents' => 12345, 'active' => false])
            ->assertOk()->assertJsonPath('data.price_cents', 12345)->assertJsonPath('data.active', false);
        $this->assertSame(8, $package->fresh()?->lesson_count);
    }

    #[Test]
    public function the_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/lesson-packages')->assertUnauthorized();
        $this->postJson('/api/v1/lesson-packages', [])->assertUnauthorized();
    }
}
