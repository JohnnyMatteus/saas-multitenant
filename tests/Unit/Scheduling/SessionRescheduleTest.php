<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Modules\Scheduling\Domain\SessionReschedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionRescheduleTest extends TestCase
{
    #[Test]
    public function it_keeps_the_local_date_and_moves_the_local_time(): void
    {
        // 07:00 em São Paulo (UTC-3) -> 18:30 local, mesma data.
        $occurrence = SessionReschedule::apply(
            CarbonImmutable::parse('2026-03-03 10:00:00', 'UTC'), 'America/Sao_Paulo', '18:30', 45,
        );

        $this->assertSame('2026-03-03 21:30:00', $occurrence->startsAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-03 22:15:00', $occurrence->endsAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_uses_the_utc_offset_of_the_session_date(): void
    {
        // Em Nova York a sessão de março (EDT, UTC-4) e a de janeiro (EST, UTC-5) têm offsets diferentes.
        $summer = SessionReschedule::apply(CarbonImmutable::parse('2026-07-01 11:00:00', 'UTC'), 'America/New_York', '08:00', 60);
        $winter = SessionReschedule::apply(CarbonImmutable::parse('2026-01-14 12:00:00', 'UTC'), 'America/New_York', '08:00', 60);

        $this->assertSame('12:00', $summer->startsAt->format('H:i'));
        $this->assertSame('13:00', $winter->startsAt->format('H:i'));
    }
}
