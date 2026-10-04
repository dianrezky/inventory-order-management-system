<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\PurchaseOrder;
use App\Repository\Fake\EventLogFakeRepository;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\ProductStockFakeRepository;
use App\Repository\Fake\PurchaseOrderFakeRepository;
use App\Repository\Fake\SalesOrderFakeRepository;
use App\Service\DashboardService;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    private function makeService(): DashboardService
    {
        return new DashboardService(
            new ProductFakeRepository([]),
            new ProductStockFakeRepository([]),
            new PurchaseOrderFakeRepository(),
            new SalesOrderFakeRepository(),
            new EventLogFakeRepository(),
        );
    }

    public function testGetAdminStatsReturnsInventoryValue(): void
    {
        $svc = $this->makeService();
        $stats = $svc->getAdminStats();

        $this->assertArrayHasKey('inventory_value', $stats);
        $this->assertArrayHasKey('low_stock_count', $stats);
        $this->assertArrayHasKey('low_stock_products', $stats);
        $this->assertArrayHasKey('po_by_status', $stats);
        $this->assertArrayHasKey('so_by_status', $stats);
    }

    public function testGetAdminStatsEmptyCountsReturnZero(): void
    {
        $svc = $this->makeService();
        $stats = $svc->getAdminStats();

        $this->assertSame(0, $stats['low_stock_count']);
        $this->assertSame([], $stats['po_by_status']);
        $this->assertSame([], $stats['so_by_status']);
    }

    public function testGetSalesStatsReturnsStatusCountsForUser(): void
    {
        $svc = $this->makeService();
        $stats = $svc->getSalesStats(userId: 5);

        $this->assertIsArray($stats);
    }

    public function testGetSalesStatsEmptyReturnsEmptyArray(): void
    {
        $svc = $this->makeService();
        $stats = $svc->getSalesStats(userId: 99);

        $this->assertSame([], $stats);
    }

    public function testGetWarehouseStatsReturnsQueueCounts(): void
    {
        $svc = $this->makeService();
        $stats = $svc->getWarehouseStats();

        $this->assertArrayHasKey('po_receipt_queue', $stats);
        $this->assertArrayHasKey('so_issue_queue', $stats);
        $this->assertArrayHasKey('low_stock_count', $stats);
        $this->assertArrayHasKey('low_stock_products', $stats);
        $this->assertSame(0, $stats['po_receipt_queue']);
        $this->assertSame(0, $stats['so_issue_queue']);
        $this->assertSame(0, $stats['low_stock_count']);
    }

    public function testGetWarehouseStatsReceiptQueueSumsOrderedAndPartiallyReceived(): void
    {
        $poRepo = new PurchaseOrderFakeRepository();
        $svc = new DashboardService(
            new ProductFakeRepository([]),
            new ProductStockFakeRepository([]),
            $poRepo,
            new SalesOrderFakeRepository(),
            new EventLogFakeRepository(),
        );

        // Manually inject PO rows into the fake repo (hack via reflection for unit test)
        $this->injectPoStatuses($poRepo, [
            'Ordered' => 3,
            'PartiallyReceived' => 2,
            'Received' => 5,
        ]);

        $stats = $svc->getWarehouseStats();

        // Receipt queue = Ordered + PartiallyReceived = 3 + 2 = 5
        $this->assertSame(5, $stats['po_receipt_queue']);
    }

    public function testGetWarehouseStatsIssueQueueCountsApprovedSOs(): void
    {
        $soRepo = new SalesOrderFakeRepository();
        $svc = new DashboardService(
            new ProductFakeRepository([]),
            new ProductStockFakeRepository([]),
            new PurchaseOrderFakeRepository(),
            $soRepo,
            new EventLogFakeRepository(),
        );

        $this->injectSoStatuses($soRepo, [
            'Approved' => 4,
            'Fulfilled' => 10,
            'Draft' => 2,
        ]);

        $stats = $svc->getWarehouseStats();

        // Issue queue = Approved SOs = 4
        $this->assertSame(4, $stats['so_issue_queue']);
    }

    private function injectPoStatuses(PurchaseOrderFakeRepository $repo, array $statusCounts): void
    {
        $reflection = new \ReflectionClass($repo);
        $prop = $reflection->getProperty('byId');
        $prop->setAccessible(true);

        $nextId = 1;
        $rows = [];
        foreach ($statusCounts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $id = $nextId++;
                $rows[$id] = new PurchaseOrder(
                    id: $id,
                    supplierId: 1,
                    destinationWarehouseId: 1,
                    status: $status,
                    orderDate: '2026-09-01',
                    note: null,
                    createdBy: 1,
                );
            }
        }
        $prop->setValue($repo, $rows);
    }

    private function injectSoStatuses(SalesOrderFakeRepository $repo, array $statusCounts): void
    {
        $reflection = new \ReflectionClass($repo);
        $prop = $reflection->getProperty('orders');
        $prop->setAccessible(true);

        $nextId = 1;
        $rows = [];
        foreach ($statusCounts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $id = $nextId++;
                $rows[] = new SalesOrder(
                    id: $id,
                    customerId: 1,
                    sourceWarehouseId: 1,
                    status: $status,
                    orderDate: '2026-09-01',
                    note: null,
                    createdBy: 5,
                );
            }
        }
        $prop->setValue($repo, $rows);
    }
}
