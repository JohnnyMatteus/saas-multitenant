<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Domain\OrderStatus;
use App\Modules\Billing\Models\LessonPackage;
use App\Modules\Billing\Models\PackageOrder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PackageOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    private User $student;

    private User $otherStudent;

    private User $admin;

    private User $instructor;

    private User $equilibrioStudent;

    private User $equilibrioAdmin;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
        $this->student = $this->createUser($this->lotus, Role::Student, 'Aluna Exemplo');
        $this->otherStudent = $this->createUser($this->lotus, Role::Student, 'Aluno Caio Exemplo');
        $this->admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $this->instructor = $this->createUser($this->lotus, Role::Instructor);
        $this->equilibrioStudent = $this->createUser($this->equilibrio, Role::Student);
        $this->equilibrioAdmin = $this->createUser($this->equilibrio, Role::StudioAdmin);
        $this->package = $this->makePackage($this->lotus);
    }

    private function makePackage(Tenant $tenant, bool $active = true): LessonPackage
    {
        return LessonPackage::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Pacote 8 aulas', 'lesson_count' => 8, 'price_cents' => 32000, 'currency' => 'BRL', 'active' => $active,
        ]);
    }

    #[Test]
    public function a_student_places_an_order_that_copies_the_package_and_waits_for_payment(): void
    {
        $response = $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount_cents', 32000)
            ->assertJsonPath('data.currency', 'BRL')
            ->assertJsonPath('data.lesson_count', 8)
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.member.id', $this->student->id);

        $this->assertMatchesRegularExpression('/^pay_[0-9a-z]{26}$/', (string) $response->json('data.payment_id'));

        // Mudar o catálogo depois não altera o pedido já feito.
        $this->package->update(['price_cents' => 99999, 'active' => false]);
        $order = PackageOrder::withoutTenancy()->sole();
        $this->assertSame(32000, $order->amount_cents);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame($this->lotus->id, $order->tenant_id);
    }

    #[Test]
    public function every_order_gets_its_own_payment_reference(): void
    {
        $first = $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])->json('data.payment_id');
        $second = $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])->json('data.payment_id');

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function a_package_that_is_not_for_sale_cannot_be_ordered(): void
    {
        $this->package->update(['active' => false]);

        $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])
            ->assertUnprocessable()->assertJsonPath('type', 'package_unavailable');
        $this->assertSame(0, PackageOrder::withoutTenancy()->count());
    }

    #[Test]
    public function ordering_another_studios_package_returns_not_found_and_writes_nothing(): void
    {
        $foreign = $this->makePackage($this->equilibrio);

        $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $foreign->id])->assertNotFound();
        $this->assertSame(0, PackageOrder::withoutTenancy()->count());
    }

    #[Test]
    public function only_students_place_orders(): void
    {
        $payload = ['package_id' => $this->package->id];

        $this->actingAsJwt($this->admin)->postJson('/api/v1/package-orders', $payload)->assertForbidden();
        $this->actingAsJwt($this->instructor)->postJson('/api/v1/package-orders', $payload)->assertForbidden();
        $this->flushHeaders()->postJson('/api/v1/package-orders', $payload)->assertUnauthorized();
        $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => 'nao-e-ulid'])->assertUnprocessable();
    }

    #[Test]
    public function a_student_lists_only_their_own_orders(): void
    {
        $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])->assertCreated();
        $this->actingAsJwt($this->otherStudent)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])->assertCreated();

        $this->actingAsJwt($this->student)->getJson('/api/v1/me/package-orders')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.member.id', $this->student->id);
        $this->actingAsJwt($this->equilibrioStudent)->getJson('/api/v1/me/package-orders')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function the_admin_lists_the_studios_orders_and_never_another_studios(): void
    {
        $this->actingAsJwt($this->student)->postJson('/api/v1/package-orders', ['package_id' => $this->package->id])->assertCreated();
        $foreign = $this->makePackage($this->equilibrio);
        $this->actingAsJwt($this->equilibrioStudent)->postJson('/api/v1/package-orders', ['package_id' => $foreign->id])->assertCreated();

        $this->actingAsJwt($this->admin)->getJson('/api/v1/package-orders')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsJwt($this->equilibrioAdmin)->getJson('/api/v1/package-orders')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsJwt($this->student)->getJson('/api/v1/package-orders')->assertForbidden();
        $this->actingAsJwt($this->instructor)->getJson('/api/v1/package-orders')->assertForbidden();
        $this->flushHeaders()->getJson('/api/v1/package-orders')->assertUnauthorized();
    }
}
