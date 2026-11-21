<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\JwtFactory;
use Tests\TestCase;

/**
 * Prova, pela API, que um estúdio não lê nem altera dados de outro.
 * Recursos de outro tenant respondem 404 (não 403) para não confirmar que existem.
 */
final class TenantIsolationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private Tenant $equilibrio;

    private ClassSession $lotusSession;

    private Booking $lotusBooking;

    private User $lotusAdmin;

    private User $lotusInstructor;

    private User $equilibrioAdmin;

    private User $equilibrioStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');

        $this->lotusAdmin = $this->createUser($this->lotus, Role::StudioAdmin);
        $this->lotusInstructor = $this->createUser($this->lotus, Role::Instructor);
        $lotusStudent = $this->createUser($this->lotus, Role::Student);
        $this->lotusSession = ClassSession::factory()->create([
            'tenant_id' => $this->lotus->id,
            'instructor_id' => $this->lotusInstructor->id,
            'title' => 'Hatha Lótus',
        ]);
        $this->lotusBooking = Booking::withoutTenancy()->create([
            'tenant_id' => $this->lotus->id,
            'session_id' => $this->lotusSession->id,
            'member_id' => $lotusStudent->id,
            'status' => BookingStatus::Confirmed,
            'queued_at' => now(),
            'confirmed_at' => now(),
        ]);

        $this->equilibrioAdmin = $this->createUser($this->equilibrio, Role::StudioAdmin);
        $this->equilibrioStudent = $this->createUser($this->equilibrio, Role::Student);
        ClassSession::factory()->create(['tenant_id' => $this->equilibrio->id, 'title' => 'Pilates Solo']);
    }

    #[Test]
    public function session_listing_only_contains_the_callers_studio(): void
    {
        $this->actingAsJwt($this->equilibrioStudent)
            ->getJson('/api/v1/sessions?from='.now()->toDateString().'&to='.now()->addDays(5)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pilates Solo');
    }

    #[Test]
    public function reading_another_studios_session_returns_not_found(): void
    {
        $this->actingAsJwt($this->equilibrioStudent)
            ->getJson("/api/v1/sessions/{$this->lotusSession->id}")
            ->assertNotFound()
            ->assertJsonPath('type', 'not_found');
    }

    #[Test]
    public function reading_another_studios_roster_returns_not_found(): void
    {
        $this->actingAsJwt($this->equilibrioAdmin)
            ->getJson("/api/v1/sessions/{$this->lotusSession->id}/bookings")
            ->assertNotFound();
    }

    #[Test]
    public function booking_another_studios_session_returns_not_found_and_writes_nothing(): void
    {
        $this->actingAsJwt($this->equilibrioStudent)
            ->postJson("/api/v1/sessions/{$this->lotusSession->id}/bookings")
            ->assertNotFound();

        $this->assertSame(1, Booking::withoutTenancy()->count());
    }

    #[Test]
    public function cancelling_another_studios_booking_returns_not_found_and_changes_nothing(): void
    {
        $this->actingAsJwt($this->equilibrioAdmin)
            ->postJson("/api/v1/bookings/{$this->lotusBooking->id}/cancel")
            ->assertNotFound();

        $this->assertSame(BookingStatus::Confirmed, Booking::withoutTenancy()->findOrFail($this->lotusBooking->id)->status);
    }

    #[Test]
    public function an_admin_cannot_schedule_classes_with_another_studios_instructor(): void
    {
        $this->actingAsJwt($this->equilibrioAdmin)
            ->postJson('/api/v1/class-series', [
                'title' => 'Pilates Intruso',
                'instructor_id' => $this->lotusInstructor->id,
                'weekdays' => [1],
                'start_time' => '08:00',
                'duration_minutes' => 50,
                'capacity' => 8,
                'starts_on' => now()->addDay()->toDateString(),
                'ends_on' => now()->addDays(10)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.instructor_id.0', 'Instrutor não encontrado neste estúdio.');

        $this->assertSame(2, ClassSession::withoutTenancy()->count());
    }

    #[Test]
    public function my_bookings_never_include_other_studios(): void
    {
        $this->actingAsJwt($this->equilibrioStudent)
            ->getJson('/api/v1/me/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function the_tenant_comes_only_from_the_signed_token(): void
    {
        // Cabeçalho e query string forjados são ignorados.
        $this->actingAsJwt($this->equilibrioStudent)
            ->withHeader('X-Tenant', 'estudio-lotus')
            ->getJson('/api/v1/me?tenant=estudio-lotus')
            ->assertOk()
            ->assertJsonPath('data.studio.slug', 'pilates-equilibrio');
    }

    #[Test]
    public function the_same_identity_is_a_different_user_in_each_studio(): void
    {
        // Mesmo "sub" chegando com a claim de outro estúdio vira outro usuário,
        // sem herdar papel nem dados do primeiro.
        $token = JwtFactory::forUser($this->lotusAdmin, $this->equilibrio, ['realm_access' => ['roles' => ['student']]]);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.studio.slug', 'pilates-equilibrio')
            ->assertJsonPath('data.role', 'student');

        $this->assertSame(2, User::withoutTenancy()->where('subject', $this->lotusAdmin->subject)->count());
        $this->assertSame(Role::StudioAdmin, User::withoutTenancy()->findOrFail($this->lotusAdmin->id)->role);
    }

    #[Test]
    public function an_unknown_tenant_is_forbidden(): void
    {
        $this->withToken(JwtFactory::token(['sub' => 'x', 'tenant' => 'estudio-inexistente']))
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('type', 'tenant_not_recognized');
    }

    #[Test]
    public function an_inactive_tenant_is_forbidden(): void
    {
        $this->equilibrio->update(['active' => false]);

        $this->actingAsJwt($this->equilibrioStudent)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('type', 'tenant_not_recognized');
    }
}
