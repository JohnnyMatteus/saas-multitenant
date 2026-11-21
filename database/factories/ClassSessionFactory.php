<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Models\ClassSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Aula avulsa (sem série). O instrutor é criado no mesmo tenant da aula.
 *
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);

        return [
            'instructor_id' => fn (array $attributes) => User::factory()
                ->role(Role::Instructor)
                ->create(['tenant_id' => $attributes['tenant_id']])
                ->id,
            'title' => 'Hatha Yoga',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(60),
            'capacity' => 10,
        ];
    }
}
