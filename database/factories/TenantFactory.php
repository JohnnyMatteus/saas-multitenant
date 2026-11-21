<?php

namespace Database\Factories;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = 'estudio-'.$this->faker->unique()->lexify('??????');

        return [
            'slug' => $slug,
            'name' => 'Estúdio '.ucfirst($this->faker->word()).' Exemplo',
            'timezone' => 'America/Sao_Paulo',
            'cancellation_window_minutes' => 720,
            'active' => true,
        ];
    }
}
