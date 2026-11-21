<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus');
        $this->alice = $this->createUser($this->lotus, Role::Student, 'Alice');
    }

    private function classSession(string $startsAtUtc = '2026-03-03 10:00:00', int $minutes = 60, int $capacity = 10): ClassSession
    {
        $startsAt = CarbonImmutable::parse($startsAtUtc, 'UTC');

        return ClassSession::factory()->create([
            'tenant_id' => $this->lotus->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($minutes),
            'capacity' => $capacity,
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function book(User $user, ClassSession $session): TestResponse
    {
        return $this->actingAsJwt($user)->postJson("/api/v1/sessions/{$session->id}/bookings");
    }

    #[Test]
    public function a_student_gets_a_seat_when_there_is_room(): void
    {
        $session = $this->classSession(capacity: 2);

        $this->book($this->alice, $session)
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.session_id', $session->id);

        $this->actingAsJwt($this->alice)->getJson("/api/v1/sessions/{$session->id}")
            ->assertJsonPath('data.confirmed_count', 1)
            ->assertJsonPath('data.available_spots', 1);
    }

    #[Test]
    public function a_full_class_puts_the_student_on_the_waitlist(): void
    {
        $session = $this->classSession(capacity: 1);
        $this->book($this->createUser($this->lotus, Role::Student), $session)->assertJsonPath('data.status', 'confirmed');

        $this->book($this->alice, $session)
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted');

        $this->actingAsJwt($this->alice)->getJson("/api/v1/sessions/{$session->id}")
            ->assertJsonPath('data.available_spots', 0)
            ->assertJsonPath('data.waitlist_count', 1);
    }

    #[Test]
    public function booking_the_same_class_twice_is_a_conflict(): void
    {
        $session = $this->classSession();
        $this->book($this->alice, $session)->assertCreated();

        $this->book($this->alice, $session)
            ->assertConflict()
            ->assertJsonPath('type', 'already_booked');

        $this->assertSame(1, Booking::withoutTenancy()->count());
    }

    #[Test]
    public function booking_again_after_cancelling_reuses_the_same_row(): void
    {
        $session = $this->classSession();
        $bookingId = $this->book($this->alice, $session)->json('data.id');
        $this->actingAsJwt($this->alice)->postJson("/api/v1/bookings/{$bookingId}/cancel")->assertOk();

        $this->book($this->alice, $session)
            ->assertCreated()
            ->assertJsonPath('data.id', $bookingId)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.canceled_at', null);
    }

    #[Test]
    public function overlapping_classes_cannot_both_be_booked(): void
    {
        $this->book($this->alice, $this->classSession('2026-03-03 10:00:00', 60))->assertCreated();

        $this->book($this->alice, $this->classSession('2026-03-03 10:30:00', 60))
            ->assertConflict()
            ->assertJsonPath('type', 'overlapping_booking');
    }

    #[Test]
    public function a_waitlist_spot_also_blocks_overlapping_bookings(): void
    {
        $full = $this->classSession('2026-03-03 10:00:00', 60, capacity: 1);
        $this->book($this->createUser($this->lotus, Role::Student), $full);
        $this->book($this->alice, $full)->assertJsonPath('data.status', 'waitlisted');

        $this->book($this->alice, $this->classSession('2026-03-03 10:15:00', 30))
            ->assertConflict()
            ->assertJsonPath('type', 'overlapping_booking');
    }

    #[Test]
    public function back_to_back_classes_do_not_overlap(): void
    {
        $this->book($this->alice, $this->classSession('2026-03-03 10:00:00', 60))->assertCreated();

        $this->book($this->alice, $this->classSession('2026-03-03 11:00:00', 60))
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');
    }

    #[Test]
    public function a_cancelled_booking_frees_the_time_slot(): void
    {
        $id = $this->book($this->alice, $this->classSession('2026-03-03 10:00:00'))->json('data.id');
        $this->actingAsJwt($this->alice)->postJson("/api/v1/bookings/{$id}/cancel")->assertOk();

        $this->book($this->alice, $this->classSession('2026-03-03 10:30:00'))->assertCreated();
    }

    #[Test]
    public function a_class_that_already_started_cannot_be_booked(): void
    {
        $session = $this->classSession('2026-03-01 11:30:00', 60);

        $this->book($this->alice, $session)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'session_already_started');
    }

    #[Test]
    public function only_students_book_classes(): void
    {
        $session = $this->classSession();

        foreach ([Role::StudioAdmin, Role::Instructor] as $role) {
            $this->book($this->createUser($this->lotus, $role), $session)->assertForbidden();
        }
    }

    #[Test]
    public function an_unknown_session_returns_not_found(): void
    {
        $this->actingAsJwt($this->alice)
            ->postJson('/api/v1/sessions/01JZZZZZZZZZZZZZZZZZZZZZZZ/bookings')
            ->assertNotFound();
    }

    #[Test]
    public function students_list_their_own_bookings_in_class_order(): void
    {
        $later = $this->classSession('2026-03-05 10:00:00');
        $sooner = $this->classSession('2026-03-03 10:00:00');
        $this->book($this->alice, $later);
        $this->book($this->alice, $sooner);
        $this->book($this->createUser($this->lotus, Role::Student), $sooner);

        $this->actingAsJwt($this->alice)->getJson('/api/v1/me/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.session_id', $sooner->id)
            ->assertJsonPath('data.1.session_id', $later->id);
    }

    #[Test]
    public function rejected_and_accepted_attempts_are_counted_in_metrics(): void
    {
        $session = $this->classSession(capacity: 1);
        $this->book($this->alice, $session);
        $this->book($this->alice, $session);

        $metrics = $this->get('/metrics')->assertOk()->getContent();

        $this->assertStringContainsString('saas_bookings_total{result="confirmed"} 1', (string) $metrics);
        $this->assertStringContainsString('saas_bookings_total{result="rejected_already_booked"} 1', (string) $metrics);
    }

    #[Test]
    public function waitlist_order_is_kept_in_microseconds(): void
    {
        $session = $this->classSession(capacity: 1);
        $this->book($this->createUser($this->lotus, Role::Student), $session);
        $this->book($this->alice, $session);

        $booking = Booking::withoutTenancy()->where('member_id', $this->alice->id)->sole();

        $this->assertSame(BookingStatus::Waitlisted, $booking->status);
        $this->assertMatchesRegularExpression('/\.\d{6}$/', (string) $booking->getRawOriginal('queued_at'));
    }
}
