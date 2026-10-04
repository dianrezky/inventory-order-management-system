<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ReferenceGapScenarios;

final class GoodsIssueServiceLockOrderTest extends TestCase
{
    public function testPublicWorkflowLocksAscendingAndPreservesQuantities()
    {
        $before = ReferenceGapScenarios::$assertionCount;
        ReferenceGapScenarios::issueLockOrder();
        self::addToAssertionCount(ReferenceGapScenarios::$assertionCount - $before);
    }
}
