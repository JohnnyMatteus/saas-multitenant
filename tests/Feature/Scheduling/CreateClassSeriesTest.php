<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateClassSeriesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private User $admin;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 12:00:00');
        $this->lotus = $this->createTenant('estudio-lotus', 'America/Sao_Paulo');
        $this->admin = $this->createUser($this->lotus, Role::StudioAdmin);
        $this->instructor = $this->createUser($this->lotus, Role::Instructor, 'Igor Exemplo');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Hatha Yoga',
            'instructor_id' => $this->instructor->id,
            'weekdays' => [2, 4],
            'start_time' => '07:00',
            'duration_minutes' => 60,
            'capacity' => 12,
            'starts_on' => '2026-03-02',
            'ends_on' => '2026-03-15',
        ], $overrides);
    }

    #[Test]
    public function an_admin_creates_a_recurring_class_with_its_sessions(): void
    {
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/class-series', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.sessions_count', 4)
            ->assertJsonPath('data.weekdays', [2, 4]);

        // 07:00 em São Paulo (UTC-3) é gravado como 10:00 UTC no banco.
        $this->assertSame(
            ['2026-03-03 10:00:00', '2026-03-05 10:00:00', '2026-03-10 10:00:00', '2026-03-12 10:00:00'],
            DB::table('class_sessions')->orderBy('starts_at')->pluck('starts_at')->all(),
        );
    }

    #[Test]
    public function created_sessions_are_listed_in_the_studio_timezone(): void
    {
        $this->actingAsJwt($this->admin)->postJson('/api/v1/class-series', $this->payload())->assertCreated();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/sessions?from=2026-03-03&to=2026-03-03')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-03-03T10:00:00Z')
            ->assertJsonPath('data.0.starts_at_local', '2026-03-03T07:00:00-03:00')
            ->assertJsonPath('data.0.ends_at_local', '2026-03-03T08:00:00-03:00')
            ->assertJsonPath('data.0.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.0.instructor.name', 'Igor Exemplo')
            ->assertJsonPath('data.0.available_spots', 12);
    }

    #[Test]
    public function the_date_filter_uses_local_days_not_utc_days(): void
    {
        // 22:30 de segunda em São Paulo é 01:30 de terça em UTC.
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/class-series', $this->payload(['weekdays' => [1], 'start_time' => '22:30']))
            ->assertCreated();

        $this->actingAsJwt($this->admin)->getJson('/api/v1/sessions?from=2026-03-02&to=2026-03-02')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsJwt($this->admin)->getJson('/api/v1/sessions?from=2026-03-03&to=2026-03-03')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function instructors_and_students_cannot_create_classes(): void
    {
        foreach ([$this->instructor, $this->createUser($this->lotus, Role::Student)] as $user) {
            $this->actingAsJwt($user)
                ->postJson('/api/v1/class-series', $this->payload())
                ->assertForbidden();
        }

        $this->assertSame(0, ClassSession::withoutTenancy()->count());
    }

    #[Test]
    public function the_instructor_must_have_the_instructor_role(): void
    {
        $student = $this->createUser($this->lotus, Role::Student);

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/class-series', $this->payload(['instructor_id' => $student->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('instructor_id');
    }

    #[Test]
    public function malformed_input_is_rejected_with_field_errors(): void
    {
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/class-series', $this->payload([
                'weekdays' => [0, 8], 'start_time' => '7h', 'capacity' => 0, 'ends_on' => '2026-03-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('weekdays.0')
            ->assertJsonValidationErrorFor('start_time')
            ->assertJsonValidationErrorFor('capacity')
            ->assertJsonValidationErrorFor('ends_on');
    }

    #[Test]
    public function a_period_beyond_the_generation_horizon_is_a_domain_error(): void
    {
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/class-series', $this->payload(['ends_on' => '2026-12-31']))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'invalid_recurrence');
    }
}
