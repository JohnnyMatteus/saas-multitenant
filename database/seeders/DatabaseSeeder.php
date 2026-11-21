<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Estúdios e usuários fictícios de desenvolvimento.
 *
 * Os "subject" batem com os IDs fixos dos usuários em
 * docker/keycloak/realm-estudios.json, para que o instrutor já exista antes
 * do primeiro login (o admin cria aulas apontando para ele).
 */
class DatabaseSeeder extends Seeder
{
    private const array STUDIOS = [
        [
            'slug' => 'estudio-lotus',
            'name' => 'Estúdio Lótus Exemplo',
            'timezone' => 'America/Sao_Paulo',
            'cancellation_window_minutes' => 720,
            'users' => [
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000101', 'name' => 'Ana Admin Exemplo', 'email' => 'admin.lotus@example.com', 'role' => Role::StudioAdmin],
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000102', 'name' => 'Igor Instrutor Exemplo', 'email' => 'instrutor.lotus@example.com', 'role' => Role::Instructor],
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000103', 'name' => 'Alice Aluna Exemplo', 'email' => 'aluna.lotus@example.com', 'role' => Role::Student],
            ],
        ],
        [
            'slug' => 'pilates-equilibrio',
            'name' => 'Pilates Equilíbrio Exemplo',
            'timezone' => 'America/Manaus',
            'cancellation_window_minutes' => 240,
            'users' => [
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000201', 'name' => 'Paula Admin Exemplo', 'email' => 'admin.equilibrio@example.com', 'role' => Role::StudioAdmin],
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000202', 'name' => 'Pedro Instrutor Exemplo', 'email' => 'instrutor.equilibrio@example.com', 'role' => Role::Instructor],
                ['subject' => '6f0c3b8e-1a2d-4c5e-9f00-000000000203', 'name' => 'Bruno Aluno Exemplo', 'email' => 'aluno.equilibrio@example.com', 'role' => Role::Student],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::STUDIOS as $studio) {
            $users = $studio['users'];
            unset($studio['users']);

            $tenant = Tenant::query()->updateOrCreate(['slug' => $studio['slug']], $studio);

            foreach ($users as $user) {
                User::withoutTenancy()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'subject' => $user['subject']],
                    $user,
                );
            }
        }
    }
}
