<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Complemento do teste de arquitetura, olhando o esquema real: toda tabela com
 * coluna tenant_id tem um modelo com BelongsToTenant.
 */
final class TenantColumnModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_table_with_a_tenant_id_column_has_a_tenant_scoped_model(): void
    {
        $scopedTables = [];

        foreach (glob(app_path('Modules/*/Models/*.php')) ?: [] as $file) {
            $class = 'App\\Modules\\'.basename(dirname($file, 2)).'\\Models\\'.basename($file, '.php');

            if (is_subclass_of($class, Model::class) && in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $scopedTables[] = (new $class)->getTable();
            }
        }

        $unscoped = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if ($name !== 'migrations' && Schema::hasColumn($name, 'tenant_id') && ! in_array($name, $scopedTables, true)) {
                $unscoped[] = $name;
            }
        }

        $this->assertSame([], $unscoped, 'Tabelas com tenant_id sem modelo que use BelongsToTenant.');
    }
}
