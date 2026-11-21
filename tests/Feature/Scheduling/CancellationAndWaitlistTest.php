<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Events\WaitlistPromoted;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Janela de cancelamento do estúdio Lótus: 12 horas (720 minutos).
 * Aula às 10:00 UTC de 03/03; prazo do aluno: 22:00 UTC de 02/03.
 */
final class CancellationAndWaitlistTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private ClassSession $classSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus', cancellationWindowMinutes: 720);
        $startsAt = CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC');
        $this->classSession = ClassSession::factory()->create([
            'tenant_id' => $this->lotus->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'capacity' => 1,
        ]);
    }

    private function student(string $name): User
    {
        return $this->createUser($this->lotus, Role::Student, $name);
    }

    private function book(User $user): string
    {
        return (string) $this->actingAsJwt($user)
            ->postJson("/api/v1/sessions/{$this->classSession->id}/bookings")
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @return TestResponse<Response>
     */
    private function cancel(User $user, string $bookingId): TestResponse
    {
        return $this->actingAsJwt($user)->postJson("/api/v1/bookings/{$bookingId}/cancel");
    }

    private function statusOf(string $bookingId): BookingStatus
    {
        return Booking::withoutTenancy()->findOrFail($bookingId)->status;
    }

    #[Test]
    public function a_student_cancels_before_the_deadline(): void
    {
        $alice = $this->student('Alice');
        $id = $this->book($alice);

        $this->travelTo('2026-03-02 21:59:59');

        $this->cancel($alice, $id)
            ->assertOk()
            ->assertJsonPath('data.canceled.status', 'canceled')
            ->assertJsonPath('data.promoted', null);
    }

    #[Test]
    public function a_student_cannot_cancel_a_confirmed_seat_inside_the_window(): void
    {
        $alice = $this->student('Alice');
        $id = $this->book($alice);

        $this->travelTo('2026-03-02 22:00:00');

        $this->cancel($alice, $id)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'cancellation_window_closed');

        $this->assertSame(BookingStatus::Confirmed, $this->statusOf($id));
    }

    #[Test]
    public function the_studio_admin_can_cancel_inside_the_window(): void
    {
        $id = $this->book($this->student('Alice'));

        $this->travelTo('2026-03-03 09:00:00');

        $this->cancel($this->createUser($this->lotus, Role::StudioAdmin), $id)->assertOk();
        $this->assertSame(BookingStatus::Canceled, $this->statusOf($id));
    }

    #[Test]
    public function leaving_the_waitlist_is_allowed_inside_the_window(): void
    {
        $this->book($this->student('Alice'));
        $bruno = $this->student('Bruno');
        $id = $this->book($bruno);

        $this->travelTo('2026-03-03 09:30:00');

        $this->cancel($bruno, $id)->assertOk()->assertJsonPath('data.promoted', null);
    }

    #[Test]
    public function nobody_cancels_after_the_class_started(): void
    {
        $id = $this->book($this->student('Alice'));

        $this->travelTo('2026-03-03 10:00:00');

        $this->cancel($this->createUser($this->lotus, Role::StudioAdmin), $id)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'session_already_started');
    }

    #[Test]
    public function cancelling_a_seat_promotes_the_first_in_line(): void
    {
        Event::fake([WaitlistPromoted::class]);
        $alice = $this->student('Alice');
        $aliceBooking = $this->book($alice);
        $this->travel(1)->seconds();
        $brunoBooking = $this->book($this->student('Bruno'));
        $this->travel(1)->seconds();
        $carlaBooking = $this->book($this->student('Carla'));

        $this->cancel($alice, $aliceBooking)
            ->assertOk()
            ->assertJsonPath('data.promoted.id', $brunoBooking)
            ->assertJsonPath('data.promoted.status', 'confirmed');

        $this->assertSame(BookingStatus::Confirmed, $this->statusOf($brunoBooking));
        $this->assertSame(BookingStatus::Waitlisted, $this->statusOf($carlaBooking));
        Event::assertDispatched(
            WaitlistPromoted::class,
            fn (WaitlistPromoted $event): bool => $event->bookingId === $brunoBooking,
        );
    }

    #[Test]
    public function an_admin_late_cancellation_also_promotes(): void
    {
        $aliceBooking = $this->book($this->student('Alice'));
        $brunoBooking = $this->book($this->student('Bruno'));

        $this->travelTo('2026-03-03 09:50:00');
        $this->cancel($this->createUser($this->lotus, Role::StudioAdmin), $aliceBooking)
            ->assertOk()
            ->assertJsonPath('data.promoted.id', $brunoBooking);
    }

    #[Test]
    public function the_promotion_is_counted_in_metrics(): void
    {
        $alice = $this->student('Alice');
        $aliceBooking = $this->book($alice);
        $this->book($this->student('Bruno'));

        $this->cancel($alice, $aliceBooking)->assertOk();

        $this->assertStringContainsString('saas_waitlist_promotions_total 1', (string) $this->get('/metrics')->getContent());
    }

    #[Test]
    public function cancelling_twice_is_a_conflict(): void
    {
        $alice = $this->student('Alice');
        $id = $this->book($alice);
        $this->cancel($alice, $id)->assertOk();

        $this->cancel($alice, $id)
            ->assertConflict()
            ->assertJsonPath('type', 'booking_already_canceled');
    }

    #[Test]
    public function a_student_cannot_cancel_someone_elses_booking(): void
    {
        $id = $this->book($this->student('Alice'));

        $this->cancel($this->student('Bruno'), $id)->assertForbidden();
        $this->assertSame(BookingStatus::Confirmed, $this->statusOf($id));
    }

    #[Test]
    public function an_instructor_cannot_cancel_bookings(): void
    {
        $id = $this->book($this->student('Alice'));

        $this->cancel($this->createUser($this->lotus, Role::Instructor), $id)->assertForbidden();
    }
}
