<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\InMemoryCacheService;
use App\Repository\Fake\PermissionFakeRepository;
use App\Service\PermissionService;
use PHPUnit\Framework\TestCase;

final class PermissionServiceTest extends TestCase
{
    private function makeService($grouped = [])
    {
        $repo = new PermissionFakeRepository($grouped ?: [
            'reports.stock_ledger.view' => ['Admin', 'WarehouseStaff'],
            'reports.sales_orders.view' => ['Admin', 'Sales'],
            'reports.purchase_orders.view' => ['Admin'],
            'purchase_orders.manage' => ['Admin', 'WarehouseStaff'],
            'sales_orders.menu' => ['Admin', 'Sales'],
        ]);

        $cache = new InMemoryCacheService(false);

        return new PermissionService($repo, $cache);
    }

    public function testReportPermissionsAreSplitByRole(): void
    {
        $service = $this->makeService();

        self::assertTrue($service->roleHasPermission('Admin', 'reports.stock_ledger.view'));
        self::assertTrue($service->roleHasPermission('WarehouseStaff', 'reports.stock_ledger.view'));
        self::assertFalse($service->roleHasPermission('Sales', 'reports.stock_ledger.view'));

        self::assertTrue($service->roleHasPermission('Admin', 'reports.sales_orders.view'));
        self::assertTrue($service->roleHasPermission('Sales', 'reports.sales_orders.view'));
        self::assertFalse($service->roleHasPermission('WarehouseStaff', 'reports.sales_orders.view'));

        self::assertTrue($service->roleHasPermission('Admin', 'reports.purchase_orders.view'));
        self::assertFalse($service->roleHasPermission('Sales', 'reports.purchase_orders.view'));
        self::assertFalse($service->roleHasPermission('WarehouseStaff', 'reports.purchase_orders.view'));
    }

    public function testWarehouseStaffHasPurchaseOrdersManage(): void
    {
        $service = $this->makeService();

        self::assertTrue($service->roleHasPermission('WarehouseStaff', 'purchase_orders.manage'));
        self::assertTrue($service->roleHasPermission('Admin', 'purchase_orders.manage'));
        self::assertFalse($service->roleHasPermission('Sales', 'purchase_orders.manage'));
    }

    public function testUnknownKeyReturnsFalse(): void
    {
        $service = $this->makeService();

        self::assertFalse($service->roleHasPermission('Admin', 'not_a_real_key'));
    }

    public function testGrantedKeysForRoleReturnsAllMatchingKeys(): void
    {
        $service = $this->makeService();

        $adminKeys = $service->grantedKeysForRole('Admin');

        self::assertContains('reports.stock_ledger.view', $adminKeys);
        self::assertContains('reports.sales_orders.view', $adminKeys);
        self::assertContains('reports.purchase_orders.view', $adminKeys);
        self::assertContains('purchase_orders.manage', $adminKeys);
        self::assertContains('sales_orders.menu', $adminKeys);

        $salesKeys = $service->grantedKeysForRole('Sales');

        self::assertSame(['reports.sales_orders.view', 'sales_orders.menu'], $salesKeys);
    }
}
