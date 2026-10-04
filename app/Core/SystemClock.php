<?php

namespace App\Core;

use DateTimeImmutable;

class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
