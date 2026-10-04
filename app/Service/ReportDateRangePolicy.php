<?php

namespace App\Service;

use App\Service\Exception\InvalidStateException;

// Reporting periods must name real calendar dates and preserve start <= end.
class ReportDateRangePolicy
{
    public static function assertValid($from, $to)
    {
        foreach ([$from, $to] as $date) {
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) $date) !== 1) {
                throw new InvalidStateException('Please enter a valid start date and end date.');
            }
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
                throw new InvalidStateException('Please enter a valid start date and end date.');
            }
        }
        if ($from > $to) {
            throw new InvalidStateException('The start date cannot be after the end date.');
        }
    }
}
