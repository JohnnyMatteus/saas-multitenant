<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Um estúdio cliente do SaaS. É a raiz do isolamento: não tem tenant_id.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $timezone Fuso IANA do estúdio (ex.: America/Sao_Paulo).
 * @property string|null $logo_path Caminho do logo no disco de mídia (prefixado pelo id do tenant).
 * @property int $cancellation_window_minutes Antecedência mínima para o aluno cancelar.
 * @property bool $active
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['slug', 'name', 'timezone', 'logo_path', 'cancellation_window_minutes', 'active'];

    protected function casts(): array
    {
        return [
            'cancellation_window_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
