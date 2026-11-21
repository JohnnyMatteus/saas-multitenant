<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Exceptions\CrossTenantWrite;
use App\Modules\Tenancy\Exceptions\TenantContextMissing;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Isolamento no nível do modelo e do banco, sem HTTP.
 */
final class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    private Tenant $lotus;

    private Tenant $equilibrio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->app->make(TenantContext::class);
        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
    }

    #[Test]
    public function queries_only_see_rows_of_the_current_tenant(): void
    {
        $this->createUser($this->lotus, Role::Student, 'Alice');
        $this->createUser($this->equilibrio, Role::Student, 'Bruno');

        $names = $this->context->runAs($this->lotus, fn () => User::query()->pluck('name')->all());

        $this->assertSame(['Alice'], $names);
    }

    #[Test]
    public function find_by_id_does_not_reach_another_tenant(): void
    {
        $bruno = $this->createUser($this->equilibrio, Role::Student);

        $found = $this->context->runAs($this->lotus, fn () => User::query()->find($bruno->id));

        $this->assertNull($found);
    }

    #[Test]
    public function reading_without_a_tenant_fails_closed(): void
    {
        $this->expectException(TenantContextMissing::class);

        User::query()->count();
    }

    #[Test]
    public function creating_without_tenant_and_without_context_fails(): void
    {
        $this->expectException(TenantContextMissing::class);

        User::query()->create(['subject' => 'x', 'name' => 'Sem Tenant', 'role' => Role::Student]);
    }

    #[Test]
    public function creating_fills_the_tenant_from_the_context(): void
    {
        $user = $this->context->runAs($this->lotus, fn () => User::query()->create([
            'subject' => 'abc', 'name' => 'Alice', 'role' => Role::Student,
        ]));

        $this->assertSame($this->lotus->id, $user->tenant_id);
    }

    #[Test]
    public function creating_a_row_for_another_tenant_is_refused(): void
    {
        $this->expectException(CrossTenantWrite::class);

        $this->context->runAs($this->lotus, fn () => User::query()->create([
            'tenant_id' => $this->equilibrio->id, 'subject' => 'abc', 'name' => 'Intrusa', 'role' => Role::Student,
        ]));
    }

    #[Test]
    public function moving_a_row_to_another_tenant_is_refused(): void
    {
        $alice = $this->createUser($this->lotus, Role::Student);

        $this->expectException(CrossTenantWrite::class);

        $this->context->runAs($this->lotus, function () use ($alice): void {
            $alice->tenant_id = $this->equilibrio->id;
            $alice->save();
        });
    }

    #[Test]
    public function mass_updates_are_scoped_too(): void
    {
        $alice = $this->createUser($this->lotus, Role::Student, 'Alice');
        $bruno = $this->createUser($this->equilibrio, Role::Student, 'Bruno');

        $this->context->runAs($this->lotus, fn () => User::query()->update(['name' => 'Renomeado']));

        $this->assertSame('Renomeado', $alice->fresh()?->name);
        $this->assertSame('Bruno', User::withoutTenancy()->findOrFail($bruno->id)->name);
    }

    #[Test]
    public function run_as_restores_the_previous_tenant_even_on_failure(): void
    {
        $this->context->set($this->lotus);

        try {
            $this->context->runAs($this->equilibrio, fn () => throw new \RuntimeException('falha'));
        } catch (\RuntimeException) {
        }

        $this->assertSame($this->lotus->id, $this->context->current()->id);
    }

    #[Test]
    public function the_database_rejects_a_booking_pointing_to_another_tenants_session(): void
    {
        // Mesmo que o código erre (ex.: withoutTenancy mal usado), a FK
        // composta (session_id, tenant_id) impede a mistura no banco.
        $session = ClassSession::factory()->create(['tenant_id' => $this->lotus->id]);
        $bruno = $this->createUser($this->equilibrio, Role::Student);

        $this->expectException(QueryException::class);

        Booking::withoutTenancy()->create([
            'tenant_id' => $this->equilibrio->id,
            'session_id' => $session->id,
            'member_id' => $bruno->id,
            'status' => BookingStatus::Confirmed,
            'queued_at' => now(),
        ]);
    }
}
