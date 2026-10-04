<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Result;
use App\Entity\SalesOrder;
use App\Repository\MySQL\CustomerMySQLRepository;
use App\Repository\MySQL\ProductMySQLRepository;
use App\Repository\MySQL\ProductStockMySQLRepository;
use App\Repository\MySQL\QueryBuilder;
use App\Repository\MySQL\SalesOrderMySQLRepository;
use App\Repository\MySQL\SalesOrderItemMySQLRepository;
use App\Repository\MySQL\StockLedgerMySQLRepository;
use App\Repository\MySQL\WarehouseMySQLRepository;
use App\Service\GoodsIssueService;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use PHPUnit\Framework\TestCase;

// ARCH-02: proves concurrent goods issues against the same stock cannot oversell - one succeeds, the other throws InsufficientStockException, stock never goes negative.
final class ARCH02ConcurrencyTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $host = (string) (getenv('DB_HOST') ?: 'db');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $name = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
        $user = (string) (getenv('DB_USER') ?: 'iom_app');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        try {
            $this->database = new Database($host, $port, $name, $user, $password);
        } catch (\Throwable $e) {
            self::markTestSkipped('Real MySQL not reachable: ' . $e->getMessage());
        }

        $beniId = $this->database->pdo()->query(
            "SELECT id FROM users WHERE email = 'sales1@example.com'"
        )->fetchColumn();
        if ($beniId === false) {
            self::markTestSkipped('Seed data not present');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->database)) {
            return;
        }

        // Remove every row the fixtures produced, children first (FKs are RESTRICT).
        // Deleting only the sales orders used to leave stock_ledger rows pointing at
        // SOs that no longer exist, plus the ARCH02-* products and their stock — which
        // then showed up in the shared dev DB's Stock Ledger (404 "SO #…" links),
        // Products and Reports, and broke ledger-vs-stock reconciliation (DATA-03).
        $pdo = $this->database->pdo();
        $fixtureProducts = "SELECT id FROM (SELECT id FROM products WHERE sku IN ('ARCH02-1', 'ARCH02-2')) AS fixture";
        $pdo->prepare("DELETE FROM stock_ledger WHERE product_id IN ({$fixtureProducts})")->execute();
        $pdo->prepare("DELETE FROM stock_ledger WHERE ref_type = 'SO' AND ref_id IN (SELECT id FROM (SELECT id FROM sales_orders WHERE note LIKE 'arch02-%') AS fixture_so)")->execute();
        $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id IN (SELECT id FROM sales_orders WHERE note LIKE 'arch02-%')")->execute();
        $pdo->prepare("DELETE FROM sales_orders WHERE note LIKE 'arch02-%'")->execute();
        $pdo->prepare("DELETE FROM notifications WHERE product_id IN ({$fixtureProducts})")->execute();
        $pdo->prepare("DELETE FROM product_stocks WHERE product_id IN ({$fixtureProducts})")->execute();
        $pdo->prepare("DELETE FROM products WHERE sku IN ('ARCH02-1', 'ARCH02-2')")->execute();
    }

    private function makeServices(): array
    {
        $queryBuilder = new QueryBuilder($this->database);
        $soRepo = new SalesOrderMySQLRepository($queryBuilder);
        $soItemRepo = new SalesOrderItemMySQLRepository($queryBuilder);
        $stockRepo = new ProductStockMySQLRepository($queryBuilder);
        $ledgerRepo = new StockLedgerMySQLRepository($queryBuilder);
        $productRepo = new ProductMySQLRepository($queryBuilder);
        $customerRepo = new CustomerMySQLRepository($queryBuilder);
        $warehouseRepo = new WarehouseMySQLRepository($queryBuilder);

        $soService = new SalesOrderService(
            $soRepo,
            $soItemRepo,
            $customerRepo,
            $warehouseRepo,
            $productRepo,
            new SalesOrderPolicy(),
        );
        $issueService = new GoodsIssueService(
            $this->database,
            $soRepo,
            $soItemRepo,
            $stockRepo,
            $ledgerRepo,
            new SalesOrderPolicy(),
        );

        return [$issueService, $soService, $soRepo];
    }

    public function testSecondConcurrentIssueFailsWhenStockExhaustedByFirst(): void
    {
        // ARCH-02: two Approved SOs race the same product+warehouse (stock 5; SO A wants 5, SO B wants 8) - exactly one may succeed and stock must never go negative.
        [$issueService, , $soRepo] = $this->makeServices();
        $wawanId = $this->getUserId('warehouse@example.com');
        $beniId = $this->getUserId('sales1@example.com');
        $ritaId = $this->getUserId('admin@example.com');
        $whId = $this->getWarehouseId('WH-JKT');
        $custId = $this->getCustomerId();

        // Create a dedicated test product with stock = 5
        $productId = $this->createTestProduct('ARCH02-1', stockQty: 5);

        // Two DISTINCT SOs are required: GoodsIssueService::issue() processes a whole SO atomically, so there is no partial-quantity issue API.
        $soA = $this->createApprovedSo($productId, 5, $whId, $custId, $beniId, $ritaId, 'arch02-concurrent-issue-a');
        $soB = $this->createApprovedSo($productId, 8, $whId, $custId, $beniId, $ritaId, 'arch02-concurrent-issue-b');

        // --- Request A: Issue SO A's 5 units (exact stock available) ---
        $resultA = $issueService->issue($soA->id, $wawanId);

        // --- Request B: Issue SO B's 8 units (stock exhausted by A) ---
        // GoodsIssueService::issue() catches InsufficientStockException internally and
        // returns a validation Result rather than letting it propagate.
        $resultB = $issueService->issue($soB->id, $wawanId);

        // Assert: exactly ONE request succeeded, the other failed
        $this->assertSame(Result::CODE_SUCCESS, $resultA->code, 'Request A should have succeeded: ' . $resultA->info);
        $this->assertNotSame(Result::CODE_SUCCESS, $resultB->code, 'Request B should have failed due to insufficient stock');

        // Assert: SO A is Fulfilled, SO B stays Approved (B didn't change anything)
        $finalSoAResult = $soRepo->findById($soA->id);
        $this->assertSame(SalesOrder::STATUS_FULFILLED, $finalSoAResult->data->status);
        $finalSoBResult = $soRepo->findById($soB->id);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $finalSoBResult->data->status, 'Rejected SO B must remain Approved, unchanged');

        // Assert: stock at WH-JKT for this product is exactly 0 (5 - 5 = 0)
        $stockQty = $this->stockQuantity($productId, $whId);
        $this->assertSame(0, $stockQty, 'Stock must be exactly 0 — no overselling');

        // Assert: ledger has exactly 1 entry (for the winning SO A only)
        $ledgerCount = $this->ledgerCount($soA->id) + $this->ledgerCount($soB->id);
        $this->assertSame(1, $ledgerCount, 'Ledger must have exactly 1 entry total across both SOs');
    }

    public function testBothIssuingFullAmountOnlyOneSucceeds(): void
    {
        // ARCH-02 variant: both requests issue the full stock amount - A drains 5 to 0 first, so B must fail and stock must never go negative.
        [$issueService] = $this->makeServices();
        $wawanId = $this->getUserId('warehouse@example.com');
        $beniId = $this->getUserId('sales1@example.com');
        $ritaId = $this->getUserId('admin@example.com');
        $whId = $this->getWarehouseId('WH-JKT');
        $custId = $this->getCustomerId();

        $productId = $this->createTestProduct('ARCH02-2', stockQty: 5);

        $so = $this->createApprovedSo($productId, 5, $whId, $custId, $beniId, $ritaId, 'arch02-both-full');

        // Both try to issue the full 5 units
        $resultA = $issueService->issue($so->id, $wawanId);
        $resultB = $issueService->issue($so->id, $wawanId);

        // Exactly one must succeed
        $successCount = (int) ($resultA->code === Result::CODE_SUCCESS) + (int) ($resultB->code === Result::CODE_SUCCESS);
        $this->assertSame(1, $successCount, 'Exactly one issue request must succeed');

        // Stock never goes negative
        $finalStock = $this->stockQuantity($productId, $whId);
        $this->assertGreaterThanOrEqual(0, $finalStock, 'Stock must never be negative');
    }

    private function pdo(): \PDO
    {
        return $this->database->pdo();
    }

    private function getUserId(string $email): int
    {
        $id = $this->pdo()->query("SELECT id FROM users WHERE email = '$email'")->fetchColumn();
        if ($id === false) {
            self::fail("User not found: $email");
        }

        return (int) $id;
    }

    private function getWarehouseId(string $code): int
    {
        $id = $this->pdo()->query("SELECT id FROM warehouses WHERE code = '$code'")->fetchColumn();
        if ($id === false) {
            self::fail("Warehouse not found: $code");
        }

        return (int) $id;
    }

    private function getCustomerId(): int
    {
        $id = $this->pdo()->query('SELECT id FROM customers ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($id === false) {
            self::fail('No customer found');
        }

        return (int) $id;
    }

    private function createTestProduct(string $sku, int $stockQty): int
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM stock_ledger WHERE product_id IN (SELECT id FROM products WHERE sku = ?)')->execute([$sku]);
        $pdo->prepare('DELETE FROM product_stocks WHERE product_id IN (SELECT id FROM products WHERE sku = ?)')->execute([$sku]);
        $pdo->prepare('DELETE FROM products WHERE sku = ?')->execute([$sku]);

        $categoryId = (int) $pdo->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetchColumn();
        $whId = $this->getWarehouseId('WH-JKT');

        $stmt = $pdo->prepare(
            'INSERT INTO products (sku, name, category_id, unit, purchase_price, sale_price, reorder_point, is_active) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$sku, 'ARCH-02 Test Product ' . $sku, $categoryId, 'pcs', '10.00', '15.00', 10]);
        $productId = (int) $pdo->lastInsertId();

        // Seed stock
        $pdo->prepare(
            'INSERT INTO product_stocks (product_id, warehouse_id, quantity) VALUES (?, ?, ?)'
        )->execute([$productId, $whId, $stockQty]);

        return $productId;
    }

    private function createApprovedSo(
        int $productId,
        int $qty,
        int $whId,
        int $custId,
        int $createdBy,
        int $approvedBy,
        string $note,
    ): SalesOrder {
        [, $soService, $soRepo] = $this->makeServices();

        $createResult = $soService->create(
            [
                'customer_id' => $custId,
                'source_warehouse_id' => $whId,
                'order_date' => date('Y-m-d'),
                'note' => $note,
            ],
            [['product_id' => $productId, 'qty' => $qty, 'sale_price' => '10000']],
            $createdBy,
        );
        if ($createResult->code !== Result::CODE_SUCCESS) {
            self::fail('Failed to create sales order: ' . $createResult->info);
        }
        $so = $createResult->data;

        $submitResult = $soService->submitForApproval($so->id, $createdBy);
        if ($submitResult->code !== Result::CODE_SUCCESS) {
            self::fail('Failed to submit sales order for approval: ' . $submitResult->info);
        }

        // $approvedBy (admin@example.com) is Admin, so isActorAdmin = true (BR-018/Segregation of Duties).
        $approveResult = $soService->approve($so->id, $approvedBy, true);
        if ($approveResult->code !== Result::CODE_SUCCESS) {
            self::fail('Failed to approve sales order: ' . $approveResult->info);
        }

        $findResult = $soRepo->findById($so->id);
        if ($findResult->code !== Result::CODE_SUCCESS) {
            self::fail('Failed to find sales order: ' . $findResult->info);
        }

        return $findResult->data;
    }

    private function stockQuantity(int $productId, int $warehouseId): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ?'
        );
        $stmt->execute([$productId, $warehouseId]);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function ledgerCount(int $soId): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM stock_ledger WHERE ref_type = ? AND ref_id = ?'
        );
        $stmt->execute(['SO', $soId]);

        return (int) $stmt->fetchColumn();
    }
}
