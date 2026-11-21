<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RosterAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    private User $instructor;

    private ClassSession $classSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
        $this->instructor = $this->createUser($this->lotus, Role::Instructor);
        $this->classSession = ClassSession::factory()->create([
            'tenant_id' => $this->lotus->id,
            'instructor_id' => $this->instructor->id,
            'capacity' => 1,
        ]);

        foreach (['Alice', 'Bruno'] as $name) {
            $this->actingAsJwt($this->createUser($this->lotus, Role::Student, $name))
                ->postJson("/api/v1/sessions/{$this->classSession->id}/bookings")
                ->assertCreated();
        }
    }

    /**
     * @return TestResponse<Response>
     */
    private function roster(User $user): TestResponse
    {
        return $this->actingAsJwt($user)->getJson("/api/v1/sessions/{$this->classSession->id}/bookings");
    }

    #[Test]
    public function the_instructor_sees_the_roster_of_their_own_class(): void
    {
        $this->roster($this->instructor)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.member.name', 'Alice')
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.1.member.name', 'Bruno')
            ->assertJsonPath('data.1.status', 'waitlisted');
    }

    #[Test]
    public function the_studio_admin_sees_any_roster(): void
    {
        $this->roster($this->createUser($this->lotus, Role::StudioAdmin))->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function another_instructor_cannot_see_the_roster(): void
    {
        $this->roster($this->createUser($this->lotus, Role::Instructor))->assertForbidden();
    }

    #[Test]
    public function students_cannot_see_the_roster(): void
    {
        $this->roster($this->createUser($this->lotus, Role::Student))->assertForbidden();
    }
}
