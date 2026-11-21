<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Canceled = 'canceled';

    /**
     * Reserva que ocupa o horário do aluno (vaga ou lugar na fila).
     */
    public function isActive(): bool
    {
        return $this !== self::Canceled;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Confirmed->value, self::Waitlisted->value];
    }
}
