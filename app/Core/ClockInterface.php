<?php

namespace App\Core;

use DateTimeImmutable;

// Services must read "now" through this interface instead of calling time()/date()/strtotime()
// directly, so time-dependent logic (session expiry, issued_at/done_at timestamps, report date
// ranges, aging calculations) can be driven deterministically in tests via FakeClock.
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
