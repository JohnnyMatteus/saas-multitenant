<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    #[Test]
    public function it_picks_the_most_privileged_known_role(): void
    {
        $this->assertSame(Role::StudioAdmin, Role::highestFrom(['student', 'studio_admin', 'instructor']));
        $this->assertSame(Role::Instructor, Role::highestFrom(['offline_access', 'instructor', 'student']));
        $this->assertSame(Role::Student, Role::highestFrom(['uma_authorization', 'student']));
    }

    #[Test]
    public function it_returns_null_without_a_studio_role(): void
    {
        $this->assertNull(Role::highestFrom([]));
        $this->assertNull(Role::highestFrom(['offline_access', 'STUDENT', 'admin']));
    }
}
