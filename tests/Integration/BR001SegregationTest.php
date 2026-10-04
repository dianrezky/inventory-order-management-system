<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Result;
use App\Entity\SalesOrder;
use App\Repository\MySQL\CustomerMySQLRepository;
use App\Repository\MySQL\QueryBuilder;
use App\Repository\MySQL\ProductMySQLRepository;
use App\Repository\MySQL\SalesOrderMySQLRepository;
use App\Repository\MySQL\SalesOrderItemMySQLRepository;
use App\Repository\MySQL\WarehouseMySQLRepository;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use PHPUnit\Framework\TestCase;

// DEC-012 (Segregation of Duties) through the real service + repository layer: only Admin may approve a Sales Order, and the creator identity is irrelevant - superseding the narrower prd.md BR-001 draft.
final class BR001SegregationTest extends TestCase
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

        // Verify seed data: Beni (Sales) and Rita (Admin) exist
        $beniId = $this->database->pdo()->query(
            "SELECT id FROM users WHERE email = 'sales1@example.com'"
        )->fetchColumn();
        if ($beniId === false) {
            self::markTestSkipped('Seed data not present: sales1@example.com not found');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->database)) {
            return;
        }

        $pdo = $this->database->pdo();

        // Clean up any SOs created by this test (prefixed 'br001-')
        $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id IN (SELECT id FROM sales_orders WHERE note LIKE 'br001-%')")->execute();
        $pdo->prepare("DELETE FROM sales_orders WHERE note LIKE 'br001-%'")->execute();
    }

    private function makeServices(): array
    {
        $queryBuilder = new QueryBuilder($this->database);
        $soRepo = new SalesOrderMySQLRepository($queryBuilder);
        $soItemRepo = new SalesOrderItemMySQLRepository($queryBuilder);
        $customerRepo = new CustomerMySQLRepository($queryBuilder);
        $warehouseRepo = new WarehouseMySQLRepository($queryBuilder);
        $productRepo = new ProductMySQLRepository($queryBuilder);

        return [
            new SalesOrderService($soRepo, $soItemRepo, $customerRepo, $warehouseRepo, $productRepo, new SalesOrderPolicy()),
        ];
    }

    public function testAdminCanApproveSalesUserSo(): void
    {
        [$soService] = $this->makeServices();
        $beniId = $this->getUserId('sales1@example.com');
        $ritaId = $this->getUserId('admin@example.com');
        $whId = $this->getWarehouseId();
        $custId = $this->getCustomerId();

        // Beni creates a draft SO
        $createResult = $soService->create(
            [
                'customer_id' => $custId,
                'source_warehouse_id' => $whId,
                'order_date' => date('Y-m-d'),
                'note' => 'br001-admin-approve',
            ],
            [['product_id' => $this->getProductId(), 'qty' => 5, 'sale_price' => '10000']],
            $beniId,
        );
        self::assertSame(Result::CODE_SUCCESS, $createResult->code, 'Sales order creation must succeed');
        $so = $createResult->data;

        // Beni submits for approval
        $submitResult = $soService->submitForApproval($so->id, $beniId);
        self::assertSame(Result::CODE_SUCCESS, $submitResult->code, 'Submission for approval must succeed');
        $pending = $submitResult->data;

        // Admin (Rita) approves → OK
        $approveResult = $soService->approve($pending->id, $ritaId, isActorAdmin: true);
        self::assertSame(Result::CODE_SUCCESS, $approveResult->code, 'Admin approval must succeed: ' . $approveResult->info);
        $approved = $approveResult->data;

        $this->assertSame(SalesOrder::STATUS_APPROVED, $approved->status);
        $this->assertSame($ritaId, $approved->approvedBy);
    }

    public function testSalesCannotApproveOwnSo(): void
    {
        // Segregation of Duties (DEC-012): Sales cannot approve a Sales Order, including their own - the service layer throws SalesApprovalForbiddenException.
        [$soService] = $this->makeServices();
        $beniId = $this->getUserId('sales1@example.com');
        $whId = $this->getWarehouseId();
        $custId = $this->getCustomerId();

        // Beni creates a draft SO
        $createResult = $soService->create(
            [
                'customer_id' => $custId,
                'source_warehouse_id' => $whId,
                'order_date' => date('Y-m-d'),
                'note' => 'br001-self-approve',
            ],
            [['product_id' => $this->getProductId(), 'qty' => 3, 'sale_price' => '5000']],
            $beniId,
        );
        self::assertSame(Result::CODE_SUCCESS, $createResult->code, 'Sales order creation must succeed');
        $so = $createResult->data;

        // Beni submits for approval
        $submitResult = $soService->submitForApproval($so->id, $beniId);
        self::assertSame(Result::CODE_SUCCESS, $submitResult->code, 'Submission for approval must succeed');
        $pending = $submitResult->data;

        // Beni tries to approve their own SO → denied (not Admin). SalesOrderService::approve()
        // catches SalesApprovalForbiddenException internally and returns a validation Result.
        $approveResult = $soService->approve($pending->id, $beniId, isActorAdmin: false);
        self::assertSame(Result::CODE_VALIDATION, $approveResult->code, 'Sales must never be permitted to approve');
        self::assertSame('Only an administrator can approve a sales order.', $approveResult->info);
    }

    public function testDifferentSalesCannotApproveOtherSalesSo(): void
    {
        // Segregation of Duties (DEC-012): a DIFFERENT Sales user is also denied - the old BR-001 "any non-creator may approve" reading is exactly what DEC-012 overrides.
        [$soService] = $this->makeServices();
        $beniId = $this->getUserId('sales1@example.com');
        $graceId = $this->getUserId('sales2@example.com');
        $whId = $this->getWarehouseId();
        $custId = $this->getCustomerId();

        // Beni creates SO
        $createResult = $soService->create(
            [
                'customer_id' => $custId,
                'source_warehouse_id' => $whId,
                'order_date' => date('Y-m-d'),
                'note' => 'br001-cross-approve',
            ],
            [['product_id' => $this->getProductId(), 'qty' => 2, 'sale_price' => '8000']],
            $beniId,
        );
        self::assertSame(Result::CODE_SUCCESS, $createResult->code, 'Sales order creation must succeed');
        $so = $createResult->data;

        // Beni submits
        $submitResult = $soService->submitForApproval($so->id, $beniId);
        self::assertSame(Result::CODE_SUCCESS, $submitResult->code, 'Submission for approval must succeed');
        $pending = $submitResult->data;

        // Grace (a different Sales user, isActorAdmin=false) attempts to approve → denied.
        $approveResult = $soService->approve($pending->id, $graceId, isActorAdmin: false);
        self::assertSame(Result::CODE_VALIDATION, $approveResult->code, 'A different Sales user must also never be permitted to approve');
        self::assertSame('Only an administrator can approve a sales order.', $approveResult->info);
    }

    public function testAdminCanApproveTheirOwnSelfCreatedSo(): void
    {
        // DEC-012: creator identity plays no part in the decision, so an Admin approving an SO she personally created must be permitted.
        [$soService] = $this->makeServices();
        $ritaId = $this->getUserId('admin@example.com');
        $whId = $this->getWarehouseId();
        $custId = $this->getCustomerId();

        $createResult = $soService->create(
            [
                'customer_id' => $custId,
                'source_warehouse_id' => $whId,
                'order_date' => date('Y-m-d'),
                'note' => 'br001-admin-self-approve',
            ],
            [['product_id' => $this->getProductId(), 'qty' => 1, 'sale_price' => '9000']],
            $ritaId,
        );
        self::assertSame(Result::CODE_SUCCESS, $createResult->code, 'Sales order creation must succeed');
        $so = $createResult->data;

        $submitResult = $soService->submitForApproval($so->id, $ritaId);
        self::assertSame(Result::CODE_SUCCESS, $submitResult->code, 'Submission for approval must succeed');
        $pending = $submitResult->data;

        $approveResult = $soService->approve($pending->id, $ritaId, isActorAdmin: true);
        self::assertSame(Result::CODE_SUCCESS, $approveResult->code, 'Admin self-approval must succeed: ' . $approveResult->info);
        $approved = $approveResult->data;

        $this->assertSame(SalesOrder::STATUS_APPROVED, $approved->status);
        $this->assertSame($ritaId, $approved->approvedBy);
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

    private function getWarehouseId(): int
    {
        $id = $this->pdo()->query('SELECT id FROM warehouses ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($id === false) {
            self::fail('No warehouse found');
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

    private function getProductId(): int
    {
        $id = $this->pdo()->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($id === false) {
            self::fail('No product found');
        }

        return (int) $id;
    }
}
