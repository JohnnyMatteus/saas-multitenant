<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guarda das fronteiras do monólito modular: cada módulo só importa os
 * módulos permitidos. Quebrar uma regra aqui é decisão de arquitetura, não
 * detalhe de implementação, e pede um ADR.
 */
final class ModuleBoundariesTest extends TestCase
{
    private const array ALLOWED = [
        'Shared' => [],
        'Tenancy' => ['Shared'],
        'Observability' => ['Shared'],
        'Identity' => ['Shared', 'Tenancy'],
        'Scheduling' => ['Shared', 'Tenancy', 'Identity', 'Observability'],
        'Branding' => ['Shared', 'Tenancy', 'Identity'],
        'Billing' => ['Shared', 'Tenancy', 'Identity', 'Observability'],
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function modules(): iterable
    {
        foreach (array_keys(self::ALLOWED) as $module) {
            yield $module => [$module];
        }
    }

    #[Test]
    #[DataProvider('modules')]
    public function module_only_depends_on_allowed_modules(string $module): void
    {
        $allowed = [...self::ALLOWED[$module], $module];
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../../../app/Modules/{$module}"));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/^use App\\\\Modules\\\\(\w+)\\\\/m', (string) file_get_contents($file->getPathname()), $matches);
            foreach (array_diff(array_unique($matches[1]), $allowed) as $forbidden) {
                $violations[] = "{$file->getFilename()} -> {$forbidden}";
            }
        }

        $this->assertSame([], $violations, "{$module} importa módulos não permitidos.");
    }

    #[Test]
    public function every_module_directory_has_a_declared_rule(): void
    {
        $directories = array_map('basename', glob(__DIR__.'/../../../app/Modules/*', GLOB_ONLYDIR) ?: []);

        $this->assertEqualsCanonicalizing(array_keys(self::ALLOWED), $directories);
    }
}
