<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class SeriesEditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private User $admin;

    /** @var array<int, string> Aulas da série em ordem: 03-03, 03-05, 03-10, 03-12 (10:00 UTC). */
    private array $sessionIds;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus');
        $this->admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $instructor = $this->createUser($this->lotus, Role::Instructor);

        $this->actingAsJwt($this->admin)->postJson('/api/v1/class-series', [
            'title' => 'Hatha Yoga', 'instructor_id' => $instructor->id, 'weekdays' => [2, 4],
            'start_time' => '07:00', 'duration_minutes' => 60, 'capacity' => 2,
            'starts_on' => '2026-03-02', 'ends_on' => '2026-03-15',
        ])->assertCreated();

        $this->sessionIds = DB::table('class_sessions')->orderBy('starts_at')->pluck('id')->all();
    }

    private function book(string $sessionId, ?User $student = null): string
    {
        $student ??= $this->createUser($this->lotus, Role::Student);

        return (string) $this->actingAsJwt($student)->postJson("/api/v1/sessions/{$sessionId}/bookings")
            ->assertCreated()->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function edit(string $sessionId, string $scope, array $body): TestResponse
    {
        return $this->actingAsJwt($this->admin)->patchJson("/api/v1/sessions/{$sessionId}", ['scope' => $scope, ...$body]);
    }

    /**
     * @return array<int, mixed>
     */
    private function titles(): array
    {
        return DB::table('class_sessions')->orderBy('starts_at')->pluck('title')->all();
    }

    #[Test]
    public function the_occurrence_scope_changes_only_that_session(): void
    {
        $this->edit($this->sessionIds[1], 'occurrence', ['title' => 'Yoga Restaurativa'])
            ->assertOk()->assertJsonPath('data.updated', [$this->sessionIds[1]]);

        $this->assertSame(['Hatha Yoga', 'Yoga Restaurativa', 'Hatha Yoga', 'Hatha Yoga'], $this->titles());
        $this->assertSame(1, DB::table('class_series')->count());
    }

    #[Test]
    public function the_following_scope_splits_the_series_and_keeps_the_past_untouched(): void
    {
        $response = $this->edit($this->sessionIds[1], 'following', ['title' => 'Vinyasa', 'start_time' => '18:00'])->assertOk();

        $this->assertSame(['Hatha Yoga', 'Vinyasa', 'Vinyasa', 'Vinyasa'], $this->titles());
        // 18:00 em São Paulo = 21:00 UTC; a primeira aula segue às 07:00.
        $this->assertSame(
            ['2026-03-03 10:00:00', '2026-03-05 21:00:00', '2026-03-10 21:00:00', '2026-03-12 21:00:00'],
            DB::table('class_sessions')->orderBy('starts_at')->pluck('starts_at')->all(),
        );

        $this->assertSame(2, DB::table('class_series')->count());
        $followingId = $response->json('data.series_id');
        $this->assertSame('2026-03-04', DB::table('class_series')->where('title', 'Hatha Yoga')->value('ends_on'));
        $this->assertSame('2026-03-05', DB::table('class_series')->where('id', $followingId)->value('starts_on'));
        $this->assertSame('2026-03-15', DB::table('class_series')->where('id', $followingId)->value('ends_on'));
        $this->assertSame(3, DB::table('class_sessions')->where('series_id', $followingId)->count());
    }

    #[Test]
    public function the_all_scope_updates_every_future_session_and_the_series(): void
    {
        $this->edit($this->sessionIds[2], 'all', ['title' => 'Yoga Flow', 'capacity' => 8])->assertOk();

        $this->assertSame(array_fill(0, 4, 'Yoga Flow'), $this->titles());
        $this->assertSame(1, DB::table('class_series')->count());
        $this->assertSame('Yoga Flow', DB::table('class_series')->value('title'));
        $this->assertSame([8, 8, 8, 8], DB::table('class_sessions')->orderBy('starts_at')->pluck('capacity')->map(intval(...))->all());
    }

    #[Test]
    public function sessions_with_bookings_keep_their_time_and_report_why(): void
    {
        $this->book($this->sessionIds[1]);

        $this->edit($this->sessionIds[0], 'all', ['start_time' => '08:00', 'title' => 'Yoga Manhã'])
            ->assertOk()
            ->assertJsonPath('data.skipped.0.session_id', $this->sessionIds[1])
            ->assertJsonPath('data.skipped.0.reasons', ['has_bookings']);

        $this->assertSame(
            ['2026-03-03 11:00:00', '2026-03-05 10:00:00', '2026-03-10 11:00:00', '2026-03-12 11:00:00'],
            DB::table('class_sessions')->orderBy('starts_at')->pluck('starts_at')->all(),
        );
        $this->assertSame(array_fill(0, 4, 'Yoga Manhã'), $this->titles());
    }

    #[Test]
    public function capacity_never_drops_below_the_confirmed_seats(): void
    {
        $this->book($this->sessionIds[0]);
        $this->book($this->sessionIds[0]);

        $this->edit($this->sessionIds[0], 'occurrence', ['capacity' => 1])
            ->assertOk()
            ->assertJsonPath('data.skipped.0.reasons', ['capacity_below_confirmed']);

        $this->assertSame(2, (int) DB::table('class_sessions')->where('id', $this->sessionIds[0])->value('capacity'));
    }

    #[Test]
    public function raising_the_capacity_promotes_the_waitlist(): void
    {
        $this->book($this->sessionIds[0]);
        $this->book($this->sessionIds[0]);
        $waiting = $this->book($this->sessionIds[0]);

        $this->edit($this->sessionIds[0], 'occurrence', ['capacity' => 3])->assertOk();

        $this->assertSame('confirmed', DB::table('bookings')->where('id', $waiting)->value('status'));
    }

    #[Test]
    public function students_cannot_edit_and_other_studios_get_404(): void
    {
        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->patchJson("/api/v1/sessions/{$this->sessionIds[0]}", ['scope' => 'all', 'title' => 'X'])
            ->assertForbidden();

        $other = $this->createTenant('estudio-equilibrio');
        $this->actingAsJwt($this->createUser($other, Role::StudioAdmin))
            ->patchJson("/api/v1/sessions/{$this->sessionIds[0]}", ['scope' => 'all', 'title' => 'X'])
            ->assertNotFound();

        $this->assertSame(array_fill(0, 4, 'Hatha Yoga'), $this->titles());
    }

    #[Test]
    public function an_instructor_from_another_studio_is_rejected(): void
    {
        $outsider = $this->createUser($this->createTenant('estudio-equilibrio'), Role::Instructor);

        $this->edit($this->sessionIds[0], 'all', ['instructor_id' => $outsider->id])->assertUnprocessable();
    }

    #[Test]
    public function an_empty_edit_is_rejected(): void
    {
        $this->edit($this->sessionIds[0], 'all', [])->assertUnprocessable();
    }
}
