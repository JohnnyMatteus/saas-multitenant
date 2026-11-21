<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use App\Modules\Scheduling\Domain\Exceptions\InvalidRecurrence;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

/**
 * Regra de recorrência semanal de uma aula, expressa no relógio do estúdio.
 *
 * "Terças e quintas às 07:00" significa 07:00 no fuso do estúdio em todas as
 * datas, mesmo quando o deslocamento UTC muda (horário de verão). Por isso a
 * conversão para UTC é feita data a data, nunca somando 7 dias a um instante.
 */
final readonly class WeeklyRecurrence
{
    public const int MAX_SPAN_DAYS = 183;

    public const int MIN_DURATION_MINUTES = 15;

    public const int MAX_DURATION_MINUTES = 240;

    /** @var list<int> */
    public array $weekdays;

    private CarbonImmutable $firstDay;

    private CarbonImmutable $lastDay;

    private int $hour;

    private int $minute;

    /**
     * @param  list<int>  $weekdays  Dias ISO-8601: 1 = segunda ... 7 = domingo.
     * @param  string  $startTime  Horário local "HH:MM".
     * @param  string  $startsOn  Data local "Y-m-d" (inclusiva).
     * @param  string  $endsOn  Data local "Y-m-d" (inclusiva).
     * @param  string  $timezone  Fuso IANA do estúdio.
     */
    public function __construct(
        array $weekdays,
        public string $startTime,
        public int $durationMinutes,
        public string $startsOn,
        public string $endsOn,
        public string $timezone,
    ) {
        $weekdays = array_values(array_unique($weekdays));
        sort($weekdays);

        if ($weekdays === [] || min($weekdays) < 1 || max($weekdays) > 7) {
            throw InvalidRecurrence::because('dias da semana devem estar entre 1 (segunda) e 7 (domingo)');
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $startTime, $time) !== 1) {
            throw InvalidRecurrence::because('horário deve estar no formato HH:MM');
        }

        if ($durationMinutes < self::MIN_DURATION_MINUTES || $durationMinutes > self::MAX_DURATION_MINUTES) {
            throw InvalidRecurrence::because('duração fora do intervalo permitido');
        }

        try {
            $zone = new DateTimeZone($timezone);
            $first = CarbonImmutable::createFromFormat('!Y-m-d', $startsOn, $zone);
            $last = CarbonImmutable::createFromFormat('!Y-m-d', $endsOn, $zone);
        } catch (Throwable) {
            throw InvalidRecurrence::because('datas ou fuso horário inválidos');
        }

        // createFromFormat aceita "2026-02-30" e transborda para março; o
        // round-trip recusa datas que não existem no calendário.
        if (! $first instanceof CarbonImmutable || ! $last instanceof CarbonImmutable
            || $first->format('Y-m-d') !== $startsOn || $last->format('Y-m-d') !== $endsOn) {
            throw InvalidRecurrence::because('datas inválidas');
        }

        if ($last->lessThan($first)) {
            throw InvalidRecurrence::because('data final anterior à inicial');
        }

        if ($first->diffInDays($last) > self::MAX_SPAN_DAYS) {
            throw InvalidRecurrence::because('período maior que o horizonte de geração de aulas');
        }

        $this->weekdays = $weekdays;
        $this->firstDay = $first;
        $this->lastDay = $last;
        $this->hour = (int) $time[1];
        $this->minute = (int) $time[2];
    }

    /**
     * @return list<Occurrence>
     */
    public function occurrences(): array
    {
        $occurrences = [];

        for ($day = $this->firstDay; $day->lessThanOrEqualTo($this->lastDay); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $this->weekdays, true)) {
                continue;
            }

            // Horário inexistente (salto do horário de verão) avança para o
            // primeiro instante válido, comportamento padrão do PHP.
            $startsAt = $day->setTime($this->hour, $this->minute)->utc();
            $occurrences[] = new Occurrence($startsAt, $startsAt->addMinutes($this->durationMinutes));
        }

        return $occurrences;
    }
}
