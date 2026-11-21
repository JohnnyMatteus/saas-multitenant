<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Todo modelo que carrega tenant_id precisa do trait BelongsToTenant; sem ele,
 * o modelo não tem global scope e vaza dados entre estúdios. Pega o esquecimento
 * no code review antes do banco.
 */
final class TenantModelsTest extends TestCase
{
    /**
     * @return list<class-string<Model>>
     */
    private function modelClasses(): array
    {
        $classes = [];

        foreach (glob(__DIR__.'/../../../app/Modules/*/Models/*.php') ?: [] as $file) {
            $module = basename(dirname($file, 2));
            $class = "App\\Modules\\{$module}\\Models\\".basename($file, '.php');

            if (is_subclass_of($class, Model::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @param  class-string  $class
     */
    private function declaresTenantId(string $class): bool
    {
        $reflection = new ReflectionClass($class);
        $fillable = $reflection->getDefaultProperties()['fillable'] ?? [];

        return in_array('tenant_id', (array) $fillable, true)
            || str_contains((string) $reflection->getDocComment(), '$tenant_id');
    }

    /**
     * @param  class-string  $class
     */
    private function usesTrait(string $class, string $trait): bool
    {
        return in_array($trait, class_uses_recursive($class), true);
    }

    #[Test]
    public function the_scan_finds_the_models(): void
    {
        $this->assertGreaterThanOrEqual(5, count($this->modelClasses()));
    }

    #[Test]
    public function every_model_with_tenant_id_uses_belongs_to_tenant(): void
    {
        $violations = [];

        foreach ($this->modelClasses() as $class) {
            if ($this->declaresTenantId($class) && ! $this->usesTrait($class, BelongsToTenant::class)) {
                $violations[] = $class;
            }
        }

        $this->assertSame([], $violations, 'Modelos com tenant_id sem o trait BelongsToTenant.');
    }

    #[Test]
    public function belongs_to_tenant_is_only_used_by_models_that_declare_tenant_id(): void
    {
        $misplaced = [];

        foreach ($this->modelClasses() as $class) {
            if ($this->usesTrait($class, BelongsToTenant::class) && ! $this->declaresTenantId($class)) {
                $misplaced[] = $class;
            }
        }

        $this->assertSame([], $misplaced, 'Modelos com o trait mas sem tenant_id declarado em fillable ou docblock.');
    }
}
