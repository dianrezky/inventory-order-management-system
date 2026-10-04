<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ReferenceGapScenarios;

final class GoodsReceiptServiceLockOrderTest extends TestCase
{
    public function testPublicWorkflowLocksAscendingAndPreservesQuantities()
    {
        $before = ReferenceGapScenarios::$assertionCount;
        ReferenceGapScenarios::receiptLockOrder();
        self::addToAssertionCount(ReferenceGapScenarios::$assertionCount - $before);
    }
}
