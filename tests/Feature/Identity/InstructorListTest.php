<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InstructorListTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $lotus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lotus = $this->createTenant('estudio-lotus');
    }

    #[Test]
    public function the_admin_lists_only_the_instructors_of_the_studio(): void
    {
        $this->createUser($this->lotus, Role::Instructor, 'Igor Instrutor');
        $this->createUser($this->lotus, Role::Instructor, 'Helena Instrutora');
        $this->createUser($this->lotus, Role::Student, 'Alice Aluna');
        $this->createUser($this->createTenant('pilates-equilibrio'), Role::Instructor, 'Pedro de Outro Estúdio');

        $this->actingAsJwt($this->createUser($this->lotus, Role::StudioAdmin))
            ->getJson('/api/v1/instructors')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Helena Instrutora')
            ->assertJsonPath('data.1.name', 'Igor Instrutor')
            ->assertJsonPath('data.0.role', 'instructor');
    }

    #[Test]
    public function students_and_instructors_cannot_list_instructors(): void
    {
        foreach ([Role::Student, Role::Instructor] as $role) {
            $this->actingAsJwt($this->createUser($this->lotus, $role))
                ->getJson('/api/v1/instructors')
                ->assertForbidden();
        }
    }
}
