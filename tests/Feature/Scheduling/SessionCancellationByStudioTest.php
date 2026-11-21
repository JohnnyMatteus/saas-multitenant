<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Mail\SessionCanceledMail;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SessionCancellationByStudioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private User $admin;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus');
        $this->admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $this->session = ClassSession::factory()->create([
            'tenant_id' => $this->lotus->id, 'capacity' => 1, 'title' => 'Vinyasa Flow',
            'starts_at' => CarbonImmutable::parse('2026-03-03 10:00'), 'ends_at' => CarbonImmutable::parse('2026-03-03 11:00'),
        ]);
    }

    #[Test]
    public function canceling_notifies_enrolled_and_waitlisted_students_and_cancels_their_bookings(): void
    {
        $enrolled = $this->createUser($this->lotus, Role::Student, 'Ana Exemplo');
        $waiting = $this->createUser($this->lotus, Role::Student, 'Bia Exemplo');
        $this->actingAsJwt($enrolled)->postJson("/api/v1/sessions/{$this->session->id}/bookings")->assertCreated();
        $this->actingAsJwt($waiting)->postJson("/api/v1/sessions/{$this->session->id}/bookings")->assertCreated();

        $this->actingAsJwt($this->admin)
            ->postJson("/api/v1/sessions/{$this->session->id}/cancel", ['reason' => 'Instrutor doente'])
            ->assertOk()
            ->assertJsonPath('data.cancel_reason', 'Instrutor doente');

        $this->assertSame(0, DB::table('bookings')->whereIn('status', ['confirmed', 'waitlisted'])->count());

        $enrolledEmail = (string) $enrolled->email;
        $waitingEmail = (string) $waiting->email;
        Mail::assertSent(SessionCanceledMail::class, 2);
        Mail::assertSent(SessionCanceledMail::class, fn (SessionCanceledMail $m): bool => $m->hasTo($enrolledEmail)
            && ! $m->wasWaitlisted && $m->reason === 'Instrutor doente' && $m->startsAtLocal === '03/03/2026 às 07:00');
        Mail::assertSent(SessionCanceledMail::class, fn (SessionCanceledMail $m): bool => $m->hasTo($waitingEmail) && $m->wasWaitlisted);
    }

    #[Test]
    public function a_canceled_session_cannot_be_booked_and_the_slot_is_free_again_for_the_student(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);
        $this->actingAsJwt($student)->postJson("/api/v1/sessions/{$this->session->id}/bookings")->assertCreated();
        $this->actingAsJwt($this->admin)->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertOk();

        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->postJson("/api/v1/sessions/{$this->session->id}/bookings")
            ->assertUnprocessable()
            ->assertJsonPath('type', 'session_canceled');
    }

    #[Test]
    public function canceling_twice_is_rejected(): void
    {
        $this->actingAsJwt($this->admin)->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertOk();
        $this->actingAsJwt($this->admin)->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertUnprocessable();
    }

    #[Test]
    public function only_the_studio_admin_cancels_and_other_studios_get_404(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertForbidden();

        $other = $this->createTenant('estudio-equilibrio');
        $this->actingAsJwt($this->createUser($other, Role::StudioAdmin))
            ->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertNotFound();

        $this->assertNull(DB::table('class_sessions')->where('id', $this->session->id)->value('canceled_at'));
    }

    #[Test]
    public function a_session_that_already_started_cannot_be_canceled(): void
    {
        $this->travelTo('2026-03-03 10:30:00');

        $this->actingAsJwt($this->admin)->postJson("/api/v1/sessions/{$this->session->id}/cancel")->assertUnprocessable();
    }
}
