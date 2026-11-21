<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Scheduling\Policies\BookingPolicy;
use App\Modules\Scheduling\Policies\ClassSeriesPolicy;
use App\Modules\Scheduling\Policies\ClassSessionPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Matriz RBAC sem banco. Os casos de tenant diferente cobrem a defesa em
 * profundidade: mesmo que um registro de outro tenant escapasse do scope,
 * a policy negaria.
 */
final class PolicyTest extends TestCase
{
    private function user(Role $role, string $id = 'u1', string $tenant = 't1'): User
    {
        return (new User)->forceFill(['id' => $id, 'tenant_id' => $tenant, 'role' => $role]);
    }

    private function session(string $instructorId, string $tenant = 't1'): ClassSession
    {
        return (new ClassSession)->forceFill(['tenant_id' => $tenant, 'instructor_id' => $instructorId]);
    }

    private function booking(string $memberId, string $tenant = 't1'): Booking
    {
        return (new Booking)->forceFill(['tenant_id' => $tenant, 'member_id' => $memberId]);
    }

    #[Test]
    public function only_admins_create_class_series(): void
    {
        $policy = new ClassSeriesPolicy;

        $this->assertTrue($policy->create($this->user(Role::StudioAdmin)));
        $this->assertFalse($policy->create($this->user(Role::Instructor)));
        $this->assertFalse($policy->create($this->user(Role::Student)));
    }

    #[Test]
    public function roster_is_visible_to_admin_and_own_instructor_in_the_same_tenant(): void
    {
        $policy = new ClassSessionPolicy;

        $this->assertTrue($policy->viewRoster($this->user(Role::StudioAdmin), $this->session('x')));
        $this->assertTrue($policy->viewRoster($this->user(Role::Instructor, 'i1'), $this->session('i1')));
        $this->assertFalse($policy->viewRoster($this->user(Role::Instructor, 'i2'), $this->session('i1')));
        $this->assertFalse($policy->viewRoster($this->user(Role::Student, 'i1'), $this->session('i1')));
        $this->assertFalse($policy->viewRoster($this->user(Role::StudioAdmin, tenant: 't2'), $this->session('x')));
        $this->assertFalse($policy->viewRoster($this->user(Role::Instructor, 'i1', 't2'), $this->session('i1')));
    }

    #[Test]
    public function only_students_book(): void
    {
        $policy = new BookingPolicy;

        $this->assertTrue($policy->create($this->user(Role::Student)));
        $this->assertFalse($policy->create($this->user(Role::Instructor)));
        $this->assertFalse($policy->create($this->user(Role::StudioAdmin)));
    }

    #[Test]
    public function owner_or_admin_cancels_within_the_same_tenant(): void
    {
        $policy = new BookingPolicy;

        $this->assertTrue($policy->cancel($this->user(Role::Student, 's1'), $this->booking('s1')));
        $this->assertTrue($policy->cancel($this->user(Role::StudioAdmin), $this->booking('s1')));
        $this->assertFalse($policy->cancel($this->user(Role::Student, 's2'), $this->booking('s1')));
        $this->assertFalse($policy->cancel($this->user(Role::Instructor), $this->booking('s1')));
        $this->assertFalse($policy->cancel($this->user(Role::StudioAdmin, tenant: 't2'), $this->booking('s1')));
        $this->assertFalse($policy->cancel($this->user(Role::Student, 's1', 't2'), $this->booking('s1')));
    }
}
