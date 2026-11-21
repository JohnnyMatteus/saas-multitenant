<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Modules\Scheduling\Domain\Exceptions\InvalidRecurrence;
use App\Modules\Scheduling\Domain\Occurrence;
use App\Modules\Scheduling\Domain\WeeklyRecurrence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WeeklyRecurrenceTest extends TestCase
{
    #[Test]
    public function it_generates_one_occurrence_per_matching_weekday_including_both_ends(): void
    {
        // 2026-03-02 é segunda; terças (2) e quintas (4) até 2026-03-12 (quinta).
        $recurrence = new WeeklyRecurrence([4, 2], '07:00', 60, '2026-03-02', '2026-03-12', 'America/Sao_Paulo');

        $this->assertSame([2, 4], $recurrence->weekdays);
        $this->assertSame(
            ['2026-03-03 10:00', '2026-03-05 10:00', '2026-03-10 10:00', '2026-03-12 10:00'],
            $this->startsUtc($recurrence->occurrences()),
        );
    }

    #[Test]
    public function occurrences_are_in_utc_and_honour_duration(): void
    {
        $occurrence = (new WeeklyRecurrence([1], '18:30', 50, '2026-03-02', '2026-03-02', 'America/Manaus'))->occurrences()[0];

        $this->assertSame('UTC', $occurrence->startsAt->getTimezone()->getName());
        $this->assertSame('2026-03-02 22:30', $occurrence->startsAt->format('Y-m-d H:i'));
        $this->assertSame('2026-03-02 23:20', $occurrence->endsAt->format('Y-m-d H:i'));
    }

    #[Test]
    public function local_time_is_kept_across_daylight_saving_transitions(): void
    {
        // Lisboa muda para o horário de verão (UTC+1) em 2026-03-29.
        $recurrence = new WeeklyRecurrence([6], '07:00', 60, '2026-03-21', '2026-04-04', 'Europe/Lisbon');

        $this->assertSame(
            ['2026-03-21 07:00', '2026-03-28 07:00', '2026-04-04 06:00'],
            $this->startsUtc($recurrence->occurrences()),
        );

        foreach ($recurrence->occurrences() as $occurrence) {
            $this->assertSame('07:00', $occurrence->startsAt->setTimezone('Europe/Lisbon')->format('H:i'));
        }
    }

    #[Test]
    public function a_nonexistent_local_time_moves_forward_to_the_first_valid_instant(): void
    {
        // 2026-03-29 01:30 não existe em Lisboa (01:00 pula para 02:00).
        $occurrence = (new WeeklyRecurrence([7], '01:30', 60, '2026-03-29', '2026-03-29', 'Europe/Lisbon'))->occurrences()[0];

        $this->assertSame('2026-03-29 01:30', $occurrence->startsAt->format('Y-m-d H:i'));
        $this->assertSame('02:30', $occurrence->startsAt->setTimezone('Europe/Lisbon')->format('H:i'));
    }

    #[Test]
    public function it_returns_no_occurrences_when_no_weekday_falls_in_range(): void
    {
        // Segunda a quarta, pedindo só domingos.
        $this->assertSame([], (new WeeklyRecurrence([7], '07:00', 60, '2026-03-02', '2026-03-04', 'UTC'))->occurrences());
    }

    /**
     * @return iterable<string, array{list<int>, string, int, string, string, string}>
     */
    public static function invalidRecurrences(): iterable
    {
        yield 'sem dias' => [[], '07:00', 60, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'dia zero' => [[0], '07:00', 60, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'dia oito' => [[8], '07:00', 60, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'horário inválido' => [[1], '24:00', 60, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'horário sem zero' => [[1], '7:00', 60, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'duração curta' => [[1], '07:00', 10, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'duração longa' => [[1], '07:00', 241, '2026-03-02', '2026-03-09', 'UTC'];
        yield 'fim antes do início' => [[1], '07:00', 60, '2026-03-09', '2026-03-02', 'UTC'];
        yield 'período longo demais' => [[1], '07:00', 60, '2026-01-01', '2026-07-04', 'UTC'];
        yield 'data inexistente' => [[1], '07:00', 60, '2026-02-30', '2026-03-09', 'UTC'];
        yield 'fuso inexistente' => [[1], '07:00', 60, '2026-03-02', '2026-03-09', 'America/Atlantida'];
    }

    /**
     * @param  list<int>  $weekdays
     */
    #[Test]
    #[DataProvider('invalidRecurrences')]
    public function it_rejects_invalid_rules(array $weekdays, string $time, int $duration, string $from, string $to, string $timezone): void
    {
        $this->expectException(InvalidRecurrence::class);

        new WeeklyRecurrence($weekdays, $time, $duration, $from, $to, $timezone);
    }

    #[Test]
    public function the_maximum_span_is_accepted(): void
    {
        $recurrence = new WeeklyRecurrence([1], '07:00', 60, '2026-01-01', '2026-07-03', 'UTC');

        $this->assertCount(26, $recurrence->occurrences());
    }

    /**
     * @param  list<Occurrence>  $occurrences
     * @return list<string>
     */
    private function startsUtc(array $occurrences): array
    {
        return array_map(fn (Occurrence $o): string => $o->startsAt->format('Y-m-d H:i'), $occurrences);
    }
}
