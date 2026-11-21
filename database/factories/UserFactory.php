<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subject' => (string) Str::uuid(),
            'email' => $this->faker->unique()->userName().'@example.com',
            'name' => $this->faker->firstName().' Exemplo',
            'role' => Role::Student,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }
}
