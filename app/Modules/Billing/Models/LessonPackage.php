<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pacote de aulas à venda num estúdio (catálogo). Preço em centavos.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property int $lesson_count
 * @property int $price_cents
 * @property string $currency
 * @property bool $active
 */
class LessonPackage extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['tenant_id', 'name', 'lesson_count', 'price_cents', 'currency', 'active'];

    protected function casts(): array
    {
        return ['lesson_count' => 'integer', 'price_cents' => 'integer', 'active' => 'boolean'];
    }
}
