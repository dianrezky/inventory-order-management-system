<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Result;
use App\Repository\MySQL\ProductStockMySQLRepository;
use App\Repository\MySQL\QueryBuilder;
use App\Repository\MySQL\SalesOrderItemMySQLRepository;
use App\Repository\MySQL\SalesOrderMySQLRepository;
use App\Repository\MySQL\StockLedgerMySQLRepository;
use App\Service\GoodsIssueService;
use App\Service\SalesOrderPolicy;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// ARCH-02 (P0) / BR-008 / BR-009 / BR-010 / BR-015: two simultaneous goods issues on the same product+warehouse - exactly one must succeed, the other must fail cleanly, with no oversell and no corrupted stock.
final class GoodsIssueConcurrencyTest extends TestCase
{
    private Database $database;
    private PDO $pdo;
    private int $beniId;
    private int $graceId;
    private int $wawanId;
    private int $customerId;
    private int $warehouseId;
    private int $productId;

    protected function setUp(): void
    {
        $host = (string) (getenv('DB_HOST') ?: 'db');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $name = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
        $user = (string) (getenv('DB_USER') ?: 'iom_app');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        try {
            $this->database = new Database($host, $port, $name, $user, $password);
            $this->pdo = $this->database->pdo();
        } catch (\Throwable $e) {
            self::markTestSkipped('Real MySQL not reachable for integration test: ' . $e->getMessage());
        }

        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open() is required to spawn a genuinely concurrent second PHP process.');
        }

        $beniId = $this->pdo->query("SELECT id FROM users WHERE email = 'sales1@example.com'")->fetchColumn();
        $graceId = $this->pdo->query("SELECT id FROM users WHERE email = 'sales2@example.com'")->fetchColumn();
        $wawanId = $this->pdo->query("SELECT id FROM users WHERE email = 'warehouse@example.com'")->fetchColumn();
        if ($beniId === false || $graceId === false || $wawanId === false) {
            self::markTestSkipped('Seed users (beni/grace/wawan) not present.');
        }
        $this->beniId = (int) $beniId;
        $this->graceId = (int) $graceId;
        $this->wawanId = (int) $wawanId;

        $this->warehouseId = (int) $this->pdo->query("SELECT id FROM warehouses WHERE code = 'WH-JKT'")->fetchColumn();
        $this->customerId = (int) $this->pdo->query('SELECT id FROM customers ORDER BY id ASC LIMIT 1')->fetchColumn();

        $this->productId = $this->createTestProduct('IT-GIC-A');

        // Known, small starting stock (ADR-002's own worked example: 10 units).
        $this->pdo->prepare('DELETE FROM product_stocks WHERE product_id = ?')->execute([$this->productId]);
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_stocks (product_id, warehouse_id, quantity) VALUES (?, ?, 10)'
        );
        $stmt->execute([$this->productId, $this->warehouseId]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        $this->pdo->prepare("DELETE FROM sales_order_items WHERE product_id = ?")->execute([$this->productId]);
        $this->pdo->prepare("DELETE FROM sales_orders WHERE note LIKE 'integration-test-goods-issue-concurrency%'")->execute();
        $this->pdo->prepare('DELETE FROM stock_ledger WHERE product_id = ?')->execute([$this->productId]);
        // The low-stock cron (scripts/check-low-stock.php) can notify on this
        // zero-stock fixture mid-run; that FK would block the product delete below.
        $this->pdo->prepare('DELETE FROM notifications WHERE product_id = ?')->execute([$this->productId]);
        $this->pdo->prepare('DELETE FROM product_stocks WHERE product_id = ?')->execute([$this->productId]);
        $this->pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$this->productId]);
    }

    private function createTestProduct(string $sku): int
    {
        $this->pdo->prepare('DELETE FROM products WHERE sku = ?')->execute([$sku]);
        $categoryId = (int) $this->pdo->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (sku, name, category_id, unit, purchase_price, sale_price, reorder_point, is_active) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$sku, 'Integration Test Product ' . $sku, $categoryId, 'pcs', '10.00', '15.00', 5]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createApprovedSo(int $createdBy, int $qty, string $noteSuffix): int
    {
        $soRepo = new SalesOrderMySQLRepository(new QueryBuilder($this->database));

        $createResult = $soRepo->create(
            [
                'customer_id' => $this->customerId,
                'source_warehouse_id' => $this->warehouseId,
                'status' => 'Approved',
                'order_date' => date('Y-m-d'),
                'note' => 'integration-test-goods-issue-concurrency-' . $noteSuffix,
                'created_by' => $createdBy,
            ],
            [['product_id' => $this->productId, 'qty' => $qty, 'sale_price' => '15.00']],
        );

        return $createResult->data;
    }

    private function makeService(Database $database): GoodsIssueService
    {
        return new GoodsIssueService(
            $database,
            new SalesOrderMySQLRepository(new QueryBuilder($database)),
            new SalesOrderItemMySQLRepository(new QueryBuilder($database)),
            new ProductStockMySQLRepository(new QueryBuilder($database)),
            new StockLedgerMySQLRepository(new QueryBuilder($database)),
            new SalesOrderPolicy(),
        );
    }

    private function stockQuantity(): int
    {
        $stmt = $this->pdo->prepare('SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ?');
        $stmt->execute([$this->productId, $this->warehouseId]);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function ledgerSum(): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM stock_ledger WHERE product_id = ? AND warehouse_id = ?');
        $stmt->execute([$this->productId, $this->warehouseId]);

        return (int) $stmt->fetchColumn();
    }

    private function ledgerRowCount(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_ledger WHERE product_id = ? AND warehouse_id = ?');
        $stmt->execute([$this->productId, $this->warehouseId]);

        return (int) $stmt->fetchColumn();
    }

    private function soStatus(int $soId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
        $stmt->execute([$soId]);

        return (string) $stmt->fetchColumn();
    }

    public function testSecondConnectionBlocksOnRowLockHeldByFirst(): void
    {
        // ARCH-02 proof 1 (deterministic, non-flaky): connection A holds the exact lockForUpdate() row lock GoodsIssueService::issue() takes, and connection B - given a 1s innodb_lock_wait_timeout - must block on it.
        // Windows/Laragon has no pcntl_fork, so this manual dual-PDO-connection interleaving is the fallback sanctioned by the delivery-plan risk register.
        $host = (string) (getenv('DB_HOST') ?: 'db');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $name = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
        $user = (string) (getenv('DB_USER') ?: 'iom_app');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        $dbA = new Database($host, $port, $name, $user, $password);
        $dbB = new Database($host, $port, $name, $user, $password);
        $stockRepoA = new ProductStockMySQLRepository(new QueryBuilder($dbA));
        $stockRepoB = new ProductStockMySQLRepository(new QueryBuilder($dbB));

        // Connection A: begin transaction, acquire the row lock (exactly
        // the statement GoodsIssueService::issue() runs), do NOT commit.
        $dbA->pdo()->beginTransaction();
        $lockedByAResult = $stockRepoA->lockForUpdate($this->productId, $this->warehouseId);
        self::assertNotNull($lockedByAResult->data, 'Connection A must successfully lock the seeded stock row');
        self::assertSame(10, $lockedByAResult->data->quantity);

        // Connection B: force a short lock-wait timeout so the test does
        // not hang, then attempt the SAME lock — it must fail with a
        // lock-wait-timeout error while A still holds the row.
        $dbB->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $dbB->pdo()->beginTransaction();

        // ProductStockMySQLRepository::lockForUpdate() now catches the driver
        // exception and reports it as a failed Result (Result::CODE_INTERNAL)
        // rather than letting a raw PDOException escape — GoodsIssueService
        // still aborts/rolls back on that failed Result exactly as it would
        // have on the exception, so this asserts the same ARCH-02 guarantee
        // against the new repository contract instead of the old raw throw.
        $lockAttemptB = $stockRepoB->lockForUpdate($this->productId, $this->warehouseId);
        $blocked = $lockAttemptB->code !== Result::CODE_SUCCESS;

        if ($dbB->pdo()->inTransaction()) {
            $dbB->pdo()->rollBack();
        }

        self::assertTrue($blocked, 'Connection B must be blocked by the row lock held by connection A (ARCH-02)');

        // Release A's lock; the row must still show the original quantity
        // untouched (A never committed a mutation).
        $dbA->pdo()->rollBack();
        self::assertSame(10, $this->stockQuantity(), 'Stock must be unchanged after both connections release their locks');
    }

    public function testConcurrentGoodsIssueOnlyOneSucceeds(): void
    {
        // ARCH-02 proof 2 (real concurrency): two ACTUAL separate PHP OS processes, each with its own MySQL connection, call GoodsIssueService::issue() on the SAME product+warehouse at nearly the same instant - exactly one must succeed, with no oversell.
        // Task 4.12 invariant: afterwards product_stocks.quantity must equal initial + SUM(stock_ledger.qty) for the affected product+warehouse pair.
        // ADR-002's own worked example: stock = 10, two requests of
        // qty=7 and qty=5 (7+5=12 > 10) — classic double-spend scenario.
        $soA = $this->createApprovedSo($this->beniId, 7, 'A');
        $soB = $this->createApprovedSo($this->graceId, 5, 'B');

        $workerScript = __DIR__ . '/support/goods_issue_worker.php';
        self::assertFileExists($workerScript);

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Launch BOTH as separate OS processes, back-to-back with no
        // synchronous wait in between, so their DB work genuinely
        // overlaps — this is real concurrency, not sequential simulation.
        $procA = proc_open([$php, $workerScript, (string) $soA, (string) $this->wawanId], $descriptors, $pipesA);
        $procB = proc_open([$php, $workerScript, (string) $soB, (string) $this->wawanId], $descriptors, $pipesB);

        self::assertIsResource($procA, 'Failed to spawn worker process A');
        self::assertIsResource($procB, 'Failed to spawn worker process B');

        $outA = stream_get_contents($pipesA[1]);
        $errA = stream_get_contents($pipesA[2]);
        fclose($pipesA[1]);
        fclose($pipesA[2]);
        $exitA = proc_close($procA);

        $outB = stream_get_contents($pipesB[1]);
        $errB = stream_get_contents($pipesB[2]);
        fclose($pipesB[1]);
        fclose($pipesB[2]);
        $exitB = proc_close($procB);

        self::assertSame(0, $exitA, "Worker A crashed (stderr: {$errA})");
        self::assertSame(0, $exitB, "Worker B crashed (stderr: {$errB})");

        $resultA = json_decode(trim((string) $outA), true);
        $resultB = json_decode(trim((string) $outB), true);

        self::assertIsArray($resultA, "Worker A produced no parseable JSON. stdout={$outA} stderr={$errA}");
        self::assertIsArray($resultB, "Worker B produced no parseable JSON. stdout={$outB} stderr={$errB}");

        $successes = array_filter([$resultA, $resultB], static fn (array $r): bool => $r['ok'] === true);
        $failures = array_filter([$resultA, $resultB], static fn (array $r): bool => $r['ok'] === false);

        // --- Core ARCH-02 assertion: exactly one succeeds, one fails cleanly.
        self::assertCount(
            1,
            $successes,
            'Exactly ONE of the two concurrent Goods Issue attempts must succeed. Got: '
            . json_encode(['A' => $resultA, 'B' => $resultB]),
        );
        self::assertCount(1, $failures, 'Exactly ONE of the two concurrent Goods Issue attempts must fail cleanly.');

        // GoodsIssueService::issue() catches InsufficientStockException internally and
        // returns a validation Result rather than letting it propagate, so the worker's
        // JSON carries the Result's info message, not a raw exception class.
        $failure = array_values($failures)[0];
        self::assertArrayNotHasKey(
            'exception',
            $failure,
            'The rejected attempt must fail cleanly via a Result, not an uncaught exception: ' . json_encode($failure),
        );
        self::assertStringContainsString(
            'not enough stock',
            $failure['info'],
            'The rejected attempt must report insufficient stock',
        );

        // --- No oversell / no corruption: final stock is exactly initial
        // minus the ONE winning qty (never negative, never double-decremented).
        $winningQty = null;
        foreach ([[$soA, 7], [$soB, 5]] as [$soId, $qty]) {
            if ($this->soStatus($soId) === 'Fulfilled') {
                $winningQty = $qty;
            }
        }
        self::assertNotNull($winningQty, 'One SO must have transitioned to Fulfilled');

        $finalStock = $this->stockQuantity();
        self::assertGreaterThanOrEqual(0, $finalStock, 'Stock must never go negative (no oversell)');
        self::assertSame(10 - $winningQty, $finalStock, 'Final stock must equal initial(10) minus exactly the winning issue qty');

        // The losing SO must remain Approved (not silently advanced).
        $loserSoId = $winningQty === 7 ? $soB : $soA;
        self::assertSame('Approved', $this->soStatus($loserSoId), 'The rejected SO must remain Approved, unchanged');

        // --- Ledger reflects exactly ONE issue's worth, not both (no double-decrement, no silent loss).
        self::assertSame(1, $this->ledgerRowCount(), 'Exactly one stock_ledger row must exist for this product+warehouse');
        self::assertSame(-$winningQty, $this->ledgerSum(), 'Ledger sum must equal exactly the negative of the winning qty');

        // --- Task 4.12 invariant: product_stocks.quantity == initial + SUM(ledger.qty)
        self::assertSame(
            10 + $this->ledgerSum(),
            $this->stockQuantity(),
            'Invariant violated: product_stocks.quantity must equal initial + SUM(stock_ledger.qty)',
        );
    }

    public function testInsufficientStockRollsBackCleanlyLeavingSoApprovedAndNoLedgerEntry(): void
    {
        // Single-request sanity check for the non-concurrent insufficient-stock
        // path — requesting more than available must roll back everything.
        $soId = $this->createApprovedSo($this->beniId, 999, 'oversized');

        $service = $this->makeService($this->database);

        // GoodsIssueService::issue() has always caught InsufficientStockException
        // itself and translated it into a validation Result, never letting it
        // escape to the caller — assert on that Result rather than expecting a
        // raw exception.
        $result = $service->issue($soId, $this->wawanId);
        self::assertNotSame(Result::CODE_SUCCESS, $result->code, 'Insufficient stock must be reported as a failed Result');

        self::assertSame('Approved', $this->soStatus($soId), 'SO must remain Approved after a rejected issue');
        self::assertSame(10, $this->stockQuantity(), 'Stock must be untouched after a rejected issue');
        self::assertSame(0, $this->ledgerRowCount(), 'No ledger row must be written for a rejected issue');
    }
}
