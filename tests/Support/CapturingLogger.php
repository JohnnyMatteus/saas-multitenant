<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Guarda as linhas de log para o teste afirmar o que foi (e o que não foi) registrado.
 */
final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: string, message: string, context: array<mixed>}>
     */
    public function named(string $message): array
    {
        return array_values(array_filter($this->records, fn (array $r): bool => $r['message'] === $message));
    }
}
