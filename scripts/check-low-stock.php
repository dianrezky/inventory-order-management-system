#!/usr/bin/env php
<?php
/**
 * JOB-01 / scripts/check-low-stock.php
 *
 * Standalone CLI script — no session, no HTTP, no framework.
 * Queries product+warehouse combinations where that warehouse's stock < the product's reorder_point.
 *
 * Also creates an in-app notification (notifications table) for every low-stock
 * row found, deduplicated so re-running the script while stock stays low
 * doesn't spam the Admin/WarehouseStaff dashboard with repeats (bonus feature,
 * §2 "Scheduled Job — Notifikasi stok rendah", DIPERBOLEHKAN).
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

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbName = $_ENV['DB_NAME'] ?? 'inventory_order_management';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    // Database is the app's single PDO-instantiation point (ADR-001) — reused
    // here for the report query (via ->pdo()) and for NotificationService below.
    $database = new App\Core\Database($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
    $pdo = $database->pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: Database connection failed — " . $e->getMessage() . "\n");
    exit(1);
}

$notificationService = new App\Service\NotificationService(
    new App\Repository\MySQL\NotificationMySQLRepository(
        $database,
        new App\Repository\MySQL\QueryBuilder($database)
    )
);

echo "=== Low Stock Report ===" . PHP_EOL;
echo "Generated: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

// Product-level: a product is low-stock when its TOTAL quantity across all
// warehouses is below its reorder point (Project Brief JOB-01 "produk di bawah
// reorder point"; same basis as the dashboard and the Products list filter).
$stmt = $pdo->prepare(
    'SELECT p.id AS product_id, p.sku, p.name, COALESCE(SUM(ps.quantity), 0) AS quantity, p.reorder_point '
    . 'FROM products p '
    . 'LEFT JOIN product_stocks ps ON ps.product_id = p.id '
    . 'WHERE p.is_active = 1 '
    . 'GROUP BY p.id '
    . 'HAVING COALESCE(SUM(ps.quantity), 0) < p.reorder_point '
    . 'ORDER BY quantity ASC'
);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "No low-stock products." . PHP_EOL;
    exit(0);
}

printf("%-8s %-40s %10s %10s\n", 'SKU', 'Product', 'Stock', 'Reorder Pt');
echo str_repeat('-', 72) . PHP_EOL;

$count = 0;
$notified = 0;
foreach ($rows as $row) {
    $count++;
    printf(
        "%-8s %-40s %10s %10s\n",
        $row['sku'],
        mb_substr($row['name'], 0, 40),
        number_format((int) $row['quantity']),
        number_format((int) $row['reorder_point'])
    );

    $message = sprintf(
        '%s is below reorder point (%s in stock across all warehouses, reorder at %s).',
        $row['name'],
        number_format((int) $row['quantity']),
        number_format((int) $row['reorder_point'])
    );

    // Product-level notification (no single warehouse) — warehouse_id is null.
    $notifyResult = $notificationService->notifyLowStock((int) $row['product_id'], null, $message);
    if ($notifyResult->code === App\Core\Result::CODE_SUCCESS && $notifyResult->data !== null) {
        $notified++;
    }
}

echo PHP_EOL;
echo "Total low-stock products: $count" . PHP_EOL;
echo "New notifications created: $notified (existing unread ones were left as-is)" . PHP_EOL;
exit(0);
