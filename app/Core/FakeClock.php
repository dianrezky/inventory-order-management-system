<?php

namespace App\Core;

use DateTimeImmutable;

// Test double living in app/Core rather than tests/ so it autoloads under the same PSR-4 root
// as Database/FakeTransactionManager, with no test-only autoload entry. Returns a fixed instant
// (or whatever was set via set()) so time-dependent assertions in unit tests are deterministic.
class FakeClock implements ClockInterface
{
    private $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('2026-01-01 00:00:00');
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now)
    {
        $this->now = $now;
    }
}
