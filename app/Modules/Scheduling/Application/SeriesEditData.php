<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

/**
 * Campos editáveis de uma série; null = não alterar. Dias da semana não
 * mudam por aqui: isso exigiria criar e cancelar ocorrências.
 */
final readonly class SeriesEditData
{
    public function __construct(
        public ?string $title = null,
        public ?string $instructorId = null,
        public ?int $capacity = null,
        public ?string $startTime = null,
        public ?int $durationMinutes = null,
    ) {}

    public function changesTime(): bool
    {
        return $this->startTime !== null || $this->durationMinutes !== null;
    }
}
