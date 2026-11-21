<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Scheduling\Mail\BookingConfirmedMail;
use App\Modules\Scheduling\Mail\BookingReminderMail;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookingEmailsAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus');
    }

    private function sessionAt(Tenant $tenant, string $startsAtUtc, int $capacity = 5): ClassSession
    {
        return ClassSession::factory()->create([
            'tenant_id' => $tenant->id, 'capacity' => $capacity, 'title' => 'Vinyasa Flow',
            'starts_at' => CarbonImmutable::parse($startsAtUtc), 'ends_at' => CarbonImmutable::parse($startsAtUtc)->addHour(),
        ]);
    }

    #[Test]
    public function the_confirmation_email_goes_through_the_queue(): void
    {
        Queue::fake();
        $session = $this->sessionAt($this->lotus, '2026-03-03 10:00');

        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->postJson("/api/v1/sessions/{$session->id}/bookings")->assertCreated();

        Queue::assertPushed(CallQueuedListener::class, fn ($job) => str_contains($job->class, 'SendBookingConfirmationEmail'));
    }

    #[Test]
    public function a_confirmed_booking_gets_the_email_and_a_waitlisted_one_does_not(): void
    {
        Mail::fake();
        $session = $this->sessionAt($this->lotus, '2026-03-03 10:00', capacity: 1);
        $first = $this->createUser($this->lotus, Role::Student, 'Ana Exemplo');
        $second = $this->createUser($this->lotus, Role::Student, 'Bia Exemplo');

        $this->actingAsJwt($first)->postJson("/api/v1/sessions/{$session->id}/bookings")->assertCreated();
        $this->actingAsJwt($second)->postJson("/api/v1/sessions/{$session->id}/bookings")->assertCreated();

        $email = (string) $first->email;
        Mail::assertSent(BookingConfirmedMail::class, 1);
        Mail::assertSent(BookingConfirmedMail::class, fn (BookingConfirmedMail $m): bool => $m->hasTo($email)
            && $m->startsAtLocal === '03/03/2026 às 07:00');
    }

    #[Test]
    public function reminders_go_out_once_for_sessions_in_the_next_24_hours_in_each_studios_own_context(): void
    {
        $equilibrio = $this->createTenant('estudio-equilibrio');
        $soon = $this->sessionAt($this->lotus, '2026-03-02 10:00');
        $later = $this->sessionAt($this->lotus, '2026-03-05 10:00');
        $soonOther = $this->sessionAt($equilibrio, '2026-03-02 11:00');
        Mail::fake();

        foreach ([[$this->lotus, $soon], [$this->lotus, $later], [$equilibrio, $soonOther]] as [$tenant, $session]) {
            $this->actingAsJwt($this->createUser($tenant, Role::Student))
                ->postJson("/api/v1/sessions/{$session->id}/bookings")->assertCreated();
        }
        Mail::fake();

        Artisan::call('scheduling:maintain', ['--reminders' => true]);
        Artisan::call('scheduling:maintain', ['--reminders' => true]);

        Mail::assertSent(BookingReminderMail::class, 2);
        Mail::assertSent(BookingReminderMail::class, fn (BookingReminderMail $m): bool => str_contains($m->studioName, 'estudio-lotus'));
        Mail::assertSent(BookingReminderMail::class, fn (BookingReminderMail $m): bool => str_contains($m->studioName, 'estudio-equilibrio'));
        $this->assertSame(2, DB::table('bookings')->whereNotNull('reminder_sent_at')->count());
    }

    #[Test]
    public function no_reminder_for_canceled_bookings(): void
    {
        $session = $this->sessionAt($this->lotus, '2026-03-02 10:00');
        $student = $this->createUser($this->lotus, Role::Student);
        $bookingId = $this->actingAsJwt($student)->postJson("/api/v1/sessions/{$session->id}/bookings")->json('data.id');
        $this->actingAsJwt($student)->postJson("/api/v1/bookings/{$bookingId}/cancel")->assertOk();
        Mail::fake();

        Artisan::call('scheduling:maintain', ['--reminders' => true]);

        Mail::assertNothingSent();
    }

    #[Test]
    public function the_horizon_job_extends_rolling_series_idempotently_and_ignores_fixed_ones(): void
    {
        $admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $instructor = $this->createUser($this->lotus, Role::Instructor);
        $body = [
            'title' => 'Hatha Yoga', 'instructor_id' => $instructor->id, 'weekdays' => [2, 4], 'start_time' => '07:00',
            'duration_minutes' => 60, 'capacity' => 10, 'starts_on' => '2026-03-02', 'ends_on' => '2026-03-15',
        ];
        $rolling = $this->actingAsJwt($admin)->postJson('/api/v1/class-series', $body + ['rolling' => true])->assertCreated()->json('data.id');
        $fixed = $this->actingAsJwt($admin)->postJson('/api/v1/class-series', ['title' => 'Fixa'] + $body)->assertCreated()->json('data.id');

        Artisan::call('scheduling:maintain', ['--horizon' => true]);
        $afterFirst = DB::table('class_sessions')->where('series_id', $rolling)->count();
        Artisan::call('scheduling:maintain', ['--horizon' => true]);

        // Horizonte: 2026-03-01 + 90 dias = 2026-05-30; última quinta é 05-28.
        $this->assertSame('2026-05-30', DB::table('class_series')->where('id', $rolling)->value('ends_on'));
        $this->assertSame('2026-05-28 10:00:00', DB::table('class_sessions')->where('series_id', $rolling)->max('starts_at'));
        $this->assertSame($afterFirst, DB::table('class_sessions')->where('series_id', $rolling)->count());
        $this->assertSame(
            $afterFirst,
            DB::table('class_sessions')->where('series_id', $rolling)->distinct()->count('starts_at'),
        );
        $this->assertSame(4, DB::table('class_sessions')->where('series_id', $fixed)->count());
    }
}
