<?php

declare(strict_types=1);

// Standalone worker for the ARCH-02 concurrency test (GoodsIssueConcurrencyTest): launched via proc_open() as a genuinely separate OS process with its own PDO/MySQL connection so two GoodsIssueService::issue() calls really overlap inside MySQL. Usage: php goods_issue_worker.php <soId> <actorUserId>, printing exactly one line of JSON to stdout.

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Core\Database;
use App\Core\Result;
use App\Repository\MySQL\ProductStockMySQLRepository;
use App\Repository\MySQL\QueryBuilder;
use App\Repository\MySQL\SalesOrderItemMySQLRepository;
use App\Repository\MySQL\SalesOrderMySQLRepository;
use App\Repository\MySQL\StockLedgerMySQLRepository;
use App\Service\GoodsIssueService;
use App\Service\SalesOrderPolicy;

$soId = (int) ($argv[1] ?? 0);
$actorUserId = (int) ($argv[2] ?? 0);

$host = (string) (getenv('DB_HOST') ?: 'db');
$port = (int) (getenv('DB_PORT') ?: 3306);
$name = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
$user = (string) (getenv('DB_USER') ?: 'iom_app');
$password = (string) (getenv('DB_PASSWORD') ?: '');

try {
    // Each worker process gets its own fresh Database -> its own PDO
    // connection -> its own MySQL session. This is the "second PDO
    // connection" ADR-002 / the risk register calls for, except here
    // it truly lives in a second OS process, not just a second handle
    // inside the same PHP process.
    $database = new Database($host, $port, $name, $user, $password);

    $soRepo = new SalesOrderMySQLRepository(new QueryBuilder($database));
    $soItemRepo = new SalesOrderItemMySQLRepository(new QueryBuilder($database));
    $stockRepo = new ProductStockMySQLRepository(new QueryBuilder($database));
    $ledgerRepo = new StockLedgerMySQLRepository(new QueryBuilder($database));
    $policy = new SalesOrderPolicy();

    $service = new GoodsIssueService($database, $soRepo, $soItemRepo, $stockRepo, $ledgerRepo, $policy);

    $result = $service->issue($soId, $actorUserId);

    echo json_encode([
        'ok' => $result->code === Result::CODE_SUCCESS,
        'status' => $result->data->status ?? null,
        'info' => $result->info,
    ]) . "\n";
} catch (\Throwable $e) {
    echo json_encode([
        'ok' => false,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]) . "\n";
}
