<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ListFilters;
use PHPUnit\Framework\TestCase;

final class ListFiltersTest extends TestCase
{
    public function testStatusReturnsNullWhenMissing(): void
    {
        self::assertNull(ListFilters::status(null));
    }

    public function testStatusAcceptsSingleValidValue(): void
    {
        self::assertSame(['active'], ListFilters::status('active'));
    }

    public function testStatusRejectsInvalidSingleValue(): void
    {
        self::assertNull(ListFilters::status('bogus'));
    }

    public function testStatusFiltersOutInvalidArrayEntries(): void
    {
        self::assertSame(['active', 'inactive'], ListFilters::status(['active', 'bogus', 'inactive']));
    }

    public function testStatusReturnsNullWhenArrayHasNoValidEntries(): void
    {
        self::assertNull(ListFilters::status(['bogus', 'also-bogus']));
    }

    public function testRoleAcceptsKnownRole(): void
    {
        self::assertSame(['Admin'], ListFilters::role('Admin'));
    }

    public function testRoleRejectsUnknownRole(): void
    {
        self::assertNull(ListFilters::role('SuperUser'));
    }

    public function testRoleFiltersOutUnknownArrayEntries(): void
    {
        self::assertSame(['Admin', 'Sales'], ListFilters::role(['Admin', 'Bogus', 'Sales']));
    }

    public function testPositiveIntIdsParsesArrayAndDropsInvalid(): void
    {
        self::assertSame([1, 2], ListFilters::positiveIntIds(['1', 'not-a-number', '2', '-5', '0']));
    }

    public function testPositiveIntIdsAcceptsSingleScalar(): void
    {
        self::assertSame([3], ListFilters::positiveIntIds('3'));
    }

    public function testPositiveIntIdsReturnsNullForNonPositiveScalar(): void
    {
        self::assertNull(ListFilters::positiveIntIds('0'));
        self::assertNull(ListFilters::positiveIntIds('-1'));
        self::assertNull(ListFilters::positiveIntIds('abc'));
    }

    public function testPositiveIntIdsReturnsNullWhenMissing(): void
    {
        self::assertNull(ListFilters::positiveIntIds(null));
    }
}
