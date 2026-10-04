<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\FakeClock;
use App\Repository\Fake\ReportFakeRepository;
use App\Service\ReportService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

// Demonstrates ReportService's date-range defaulting is now driven by
// ClockInterface instead of date()/strtotime() calling the real system
// clock, so "last 30 days" and aging-in-days assertions are deterministic
// regardless of when the test suite actually runs.
final class ReportServiceClockTest extends TestCase
{
    public function testNormalizeParamsDefaultsToLast30DaysOfFakeClock(): void
    {
        $clock = new FakeClock(new DateTimeImmutable('2026-03-31'));
        $service = new ReportService(new ReportFakeRepository(), $clock);

        $params = $service->normalizeParams([]);

        self::assertSame('2026-03-02', $params['date_from']);
        self::assertSame('2026-03-31', $params['date_to']);
    }

    public function testNormalizeParamsMovesWithTheClock(): void
    {
        $clock = new FakeClock(new DateTimeImmutable('2026-01-10'));
        $service = new ReportService(new ReportFakeRepository(), $clock);

        $params = $service->normalizeParams([]);

        self::assertSame('2025-12-12', $params['date_from']);
        self::assertSame('2026-01-10', $params['date_to']);
    }
}
