#!/usr/bin/env php
<?php
/**
 * JOB-01 / scripts/check-low-stock.php
 *
 * Standalone CLI script — no session, no HTTP, no framework.
 * Delegates the actual rule (total stock across all warehouses below the
 * product's reorder_point) and the deduplicated notification side-effect to
 * App\Service\LowStockService, built through the same Container used by
 * public/index.php — so this job goes through Controller-less but still
 * Service → Repository layering, and the rule is unit-tested independently
 * of this script (see tests/Unit/LowStockServiceTest.php).
 *
 * Usage:
 *   php scripts/check-low-stock.php
 *   docker compose exec app php scripts/check-low-stock.php
 *
 * Output:
 *   If low-stock products exist: list of products with stock levels.
 *   If none: "No low-stock products." (exit 0)
 *   On error: "Error: <message>" (exit 1)
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// Load composer autoload
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php'; // NOSONAR
} else {
    fwrite(STDERR, "Error: autoload.php not found. Run 'composer install' first.\n");
    exit(1);
}

// Load .env if present
if (is_file($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$config = [
    'db' => [
        'host'     => $_ENV['DB_HOST'] ?? 'localhost',
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name'     => $_ENV['DB_NAME'] ?? 'inventory_order_management',
        'user'     => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
];

try {
    $container = new App\Core\Container($config);
    $lowStockService = $container->getLowStockService();
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: Database connection failed — " . $e->getMessage() . "\n");
    exit(1);
}

echo "=== Low Stock Report ===" . PHP_EOL;
echo "Generated: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

$checkResult = $lowStockService->checkAndNotify();

if ($checkResult->code !== App\Core\Result::CODE_SUCCESS) {
    fwrite(STDERR, "Error: " . $checkResult->info . "\n");
    exit(1);
}

$rows = $checkResult->data['rows'];
$notified = $checkResult->data['notified_count'];

if ($rows === []) {
    echo "No low-stock products." . PHP_EOL;
    exit(0);
}

printf("%-8s %-40s %10s %10s\n", 'SKU', 'Product', 'Stock', 'Reorder Pt');
echo str_repeat('-', 72) . PHP_EOL;

foreach ($rows as $row) {
    printf(
        "%-8s %-40s %10s %10s\n",
        $row['sku'],
        mb_substr($row['name'], 0, 40),
        number_format($row['quantity']),
        number_format($row['reorder_point'])
    );
}

echo PHP_EOL;
echo "Total low-stock products: " . count($rows) . PHP_EOL;
echo "New notifications created: $notified (existing unread ones were left as-is)" . PHP_EOL;
exit(0);
