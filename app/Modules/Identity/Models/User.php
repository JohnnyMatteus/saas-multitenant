<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * Espelho local de um usuário do Keycloak dentro de um tenant.
 *
 * A senha e o ciclo de vida da conta ficam no IdP; aqui só existe o que as
 * regras de domínio precisam referenciar (instrutor de uma aula, aluno de
 * uma reserva). Provisionado no primeiro request autenticado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $subject Claim "sub" do token.
 * @property string|null $email
 * @property string $name
 * @property Role $role
 */
class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['tenant_id', 'subject', 'email', 'name', 'role'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
