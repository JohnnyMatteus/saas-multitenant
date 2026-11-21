<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\WaitlistPromoted;
use App\Modules\Scheduling\Listeners\SendWaitlistPromotionEmail;
use App\Modules\Scheduling\Mail\WaitlistPromotedMail;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WaitlistPromotionEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_promotion_email_is_queued_not_sent_inline(): void
    {
        Queue::fake();
        $this->travelTo('2026-03-01 12:00:00');
        $tenant = $this->createTenant('estudio-lotus');
        $session = ClassSession::factory()->create([
            'tenant_id' => $tenant->id, 'capacity' => 1,
            'starts_at' => CarbonImmutable::parse('2026-03-03 10:00'), 'ends_at' => CarbonImmutable::parse('2026-03-03 11:00'),
        ]);
        $alice = $this->createUser($tenant, Role::Student);
        $aliceBooking = $this->actingAsJwt($alice)->postJson("/api/v1/sessions/{$session->id}/bookings")->json('data.id');
        $this->actingAsJwt($this->createUser($tenant, Role::Student))->postJson("/api/v1/sessions/{$session->id}/bookings");

        $this->actingAsJwt($alice)->postJson("/api/v1/bookings/{$aliceBooking}/cancel")->assertOk();

        Queue::assertPushed(CallQueuedListener::class, fn ($job) => $job->class === SendWaitlistPromotionEmail::class);
    }

    #[Test]
    public function the_queued_listener_restores_the_tenant_and_emails_the_student_in_local_time(): void
    {
        Mail::fake();
        $tenant = $this->createTenant('estudio-lotus', 'America/Sao_Paulo');
        $session = ClassSession::factory()->create([
            'tenant_id' => $tenant->id, 'title' => 'Vinyasa Flow',
            'starts_at' => CarbonImmutable::parse('2026-03-03 10:00'), 'ends_at' => CarbonImmutable::parse('2026-03-03 11:00'),
        ]);
        $student = $this->createUser($tenant, Role::Student, 'Bruno Exemplo');
        $booking = Booking::withoutTenancy()->create([
            'tenant_id' => $tenant->id, 'session_id' => $session->id, 'member_id' => $student->id,
            'status' => BookingStatus::Confirmed, 'queued_at' => now(), 'confirmed_at' => now(),
        ]);

        // Como no worker: nenhum tenant no contexto antes do listener rodar.
        $this->assertNull($this->app->make(TenantContext::class)->currentOrNull());

        $this->app->make(SendWaitlistPromotionEmail::class)
            ->handle(new WaitlistPromoted($tenant->id, $booking->id, $session->id, $student->id));

        $email = $student->email;
        $this->assertNotNull($email);
        Mail::assertSent(WaitlistPromotedMail::class, fn (WaitlistPromotedMail $mail): bool => $mail->hasTo($email)
            && $mail->classTitle === 'Vinyasa Flow'
            && $mail->startsAtLocal === '03/03/2026 às 07:00');
        $this->assertNull($this->app->make(TenantContext::class)->currentOrNull());
    }

    #[Test]
    public function no_email_when_the_booking_was_canceled_before_the_job_ran(): void
    {
        Mail::fake();
        $tenant = $this->createTenant('estudio-lotus');
        $session = ClassSession::factory()->create(['tenant_id' => $tenant->id]);
        $student = $this->createUser($tenant, Role::Student);
        $booking = Booking::withoutTenancy()->create([
            'tenant_id' => $tenant->id, 'session_id' => $session->id, 'member_id' => $student->id,
            'status' => BookingStatus::Canceled, 'queued_at' => now(), 'canceled_at' => now(),
        ]);

        $this->app->make(SendWaitlistPromotionEmail::class)
            ->handle(new WaitlistPromoted($tenant->id, $booking->id, $session->id, $student->id));

        Mail::assertNothingSent();
    }
}
