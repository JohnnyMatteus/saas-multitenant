<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Modules\Scheduling\Domain\BookingStatus;
use App\Modules\Scheduling\Domain\SeatAllocation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeatAllocationTest extends TestCase
{
    #[Test]
    public function it_confirms_while_there_are_free_seats(): void
    {
        $this->assertSame(BookingStatus::Confirmed, SeatAllocation::decide(0, 1));
        $this->assertSame(BookingStatus::Confirmed, SeatAllocation::decide(9, 10));
    }

    #[Test]
    public function it_waitlists_when_full_or_over_capacity(): void
    {
        $this->assertSame(BookingStatus::Waitlisted, SeatAllocation::decide(10, 10));
        // Capacidade reduzida depois de lotada: ninguém novo entra.
        $this->assertSame(BookingStatus::Waitlisted, SeatAllocation::decide(12, 10));
    }

    #[Test]
    public function only_canceled_is_inactive(): void
    {
        $this->assertTrue(BookingStatus::Confirmed->isActive());
        $this->assertTrue(BookingStatus::Waitlisted->isActive());
        $this->assertFalse(BookingStatus::Canceled->isActive());
        $this->assertSame(['confirmed', 'waitlisted'], BookingStatus::activeValues());
    }
}
