<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Decide se uma nova reserva ganha vaga ou entra na lista de espera.
 *
 * Função pura: a garantia de que $confirmedCount está atualizado vem do lock
 * pessimista na aula, feito pela camada de aplicação.
 */
final class SeatAllocation
{
    public static function decide(int $confirmedCount, int $capacity): BookingStatus
    {
        return $confirmedCount < $capacity ? BookingStatus::Confirmed : BookingStatus::Waitlisted;
    }
}
