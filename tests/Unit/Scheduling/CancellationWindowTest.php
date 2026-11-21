<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Modules\Scheduling\Domain\CancellationWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CancellationWindowTest extends TestCase
{
    private CarbonImmutable $startsAt;

    protected function setUp(): void
    {
        $this->startsAt = CarbonImmutable::parse('2026-03-10 10:00:00', 'UTC');
    }

    #[Test]
    public function it_is_open_before_the_deadline(): void
    {
        $window = new CancellationWindow(120);

        $this->assertTrue($window->isOpen($this->startsAt, $this->startsAt->subMinutes(121)));
        $this->assertTrue($window->isOpen($this->startsAt, $this->startsAt->subMinutes(120)->subSecond()));
    }

    #[Test]
    public function it_closes_exactly_at_the_deadline(): void
    {
        $window = new CancellationWindow(120);

        $this->assertEquals($this->startsAt->subMinutes(120), $window->deadlineFor($this->startsAt));
        $this->assertFalse($window->isOpen($this->startsAt, $this->startsAt->subMinutes(120)));
        $this->assertFalse($window->isOpen($this->startsAt, $this->startsAt->subMinutes(5)));
    }

    #[Test]
    public function a_zero_window_allows_cancelling_until_the_class_starts(): void
    {
        $window = new CancellationWindow(0);

        $this->assertTrue($window->isOpen($this->startsAt, $this->startsAt->subSecond()));
        $this->assertFalse($window->isOpen($this->startsAt, $this->startsAt));
    }

    #[Test]
    public function it_rejects_a_negative_window(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CancellationWindow(-1);
    }
}
