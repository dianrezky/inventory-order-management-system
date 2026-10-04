<?php

// Dependency-free smoke checks. This does not load or execute PHPUnit.
spl_autoload_register(static function ($class) {
    $roots = ['App\\' => '/app/', 'Tests\\' => '/tests/'];
    foreach ($roots as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__, 2) . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});

$failures = 0;
$scenarios = ['malformedOrderInputs', 'purchaseCancellationRace', 'salesDecisionRaces', 'receiptLockOrder', 'issueLockOrder', 'malformedReceipt', 'draftSalesEdit'];
$boundaries = ['availabilityErrors', 'reportPrecision', 'sharedScopedValuation', 'productSortingAndOrderSearch', 'isolatedUnitBoundaries', 'integrationDatabaseGuard', 'userUpdateContract', 'draftStatusRecheck', 'productReorderValidation', 'draftControllerErrors', 'exportDateRanges', 'exportDependencyFailures'];
foreach (array_merge($scenarios, $boundaries) as $scenario) {
    try {
        $class = in_array($scenario, $boundaries, true) ? \Tests\Support\ReferenceBoundaryScenarios::class : \Tests\Support\ReferenceGapScenarios::class;
        $class::$scenario();
        echo "PASS {$scenario}\n";
    } catch (\Throwable $e) {
        $failures++;
        fwrite(STDERR, "FAIL {$scenario}: {$e->getMessage()}\n");
    }
}
echo "Assertions: " . \Tests\Support\ReferenceGapScenarios::$assertionCount . "; failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
