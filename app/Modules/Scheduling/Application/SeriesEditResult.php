<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

final readonly class SeriesEditResult
{
    /**
     * @param  list<string>  $updated  Aulas que receberam a edição completa.
     * @param  list<array{session_id: string, reasons: list<string>}>  $skipped  Aulas com alguma parte preservada por causa de reservas.
     */
    public function __construct(
        public ?string $seriesId,
        public array $updated,
        public array $skipped,
    ) {}
}
