<?php

namespace Tests\Support;

use App\Core\Result;
use App\Repository\Fake\CategoryFakeRepository;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\ProductStockFakeRepository;
use App\Repository\Fake\ReportFakeRepository;
use App\Service\ProductService;
use App\Service\ReportService;

final class ReferenceBoundaryScenarios
{
    public static function availabilityController($service, $authenticated = true)
    {
        return new class($service, $authenticated) extends \App\Controller\ProductApiController {
            private $authenticated;
            public function __construct($service, $authenticated)
            {
                $this->authenticated = $authenticated;
                $this->container = new class($service) {
                    private $service;
                    public function __construct($service) { $this->service = $service; }
                    public function getProductService() { return $this->service; }
                };
            }
            protected function requireAuth()
            {
                return $this->authenticated ? null : \App\Core\Response::redirect('/login');
            }
        };
    }

    public static function availabilityErrors()
    {
        $brokenProducts = new class extends ProductFakeRepository {
            public function findBySku($sku)
            {
                $result = new Result();
                $result->code = Result::CODE_INTERNAL;
                $result->info = 'Simulated dependency failure';
                $result->data = null;

                return $result;
            }
        };
        $brokenStocks = new class extends ProductStockFakeRepository {
            public function findAllWithProduct($productId)
            {
                $result = new Result();
                $result->code = Result::CODE_INTERNAL;
                $result->info = 'Simulated stock failure';
                $result->data = null;

                return $result;
            }
        };
        foreach ([[$brokenProducts, new ProductStockFakeRepository()], [ReferenceGapScenarios::products(), $brokenStocks]] as [$products, $stocks]) {
            $service = new ProductService($products, new CategoryFakeRepository(), $stocks, new InMemoryCacheService());
            $response = self::availabilityController($service)->getAvailabilityAction('SKU-A');
            ReferenceGapScenarios::expectSame(500, $response->getStatusCode(), 'Dependency failure returns 500');
            ReferenceGapScenarios::expectSame(['error' => 'internal_error'], $response->getData(), 'Internal failure uses JSON error, without misleading stock');
        }
        $service = new ProductService(ReferenceGapScenarios::products(), new CategoryFakeRepository(), new ProductStockFakeRepository(), new InMemoryCacheService());
        ReferenceGapScenarios::expectSame(404, self::availabilityController($service)->getAvailabilityAction('MISSING')->getStatusCode(), 'Missing product returns 404');
        ReferenceGapScenarios::expectSame(401, self::availabilityController($service, false)->getAvailabilityAction('SKU-A')->getStatusCode(), 'Unauthenticated returns 401');
        $response = self::availabilityController($service)->getAvailabilityAction('SKU-A');
        ReferenceGapScenarios::expectSame(200, $response->getStatusCode(), 'Existing zero-stock product returns 200');
        ReferenceGapScenarios::expectSame(0, $response->getData()['total_stock'], 'Real zero stock remains distinct from failure');
        $service = new ProductService(ReferenceGapScenarios::products(), new CategoryFakeRepository(), ReferenceGapScenarios::stockRecorder(10), new InMemoryCacheService());
        $payload = self::availabilityController($service)->getAvailabilityAction('SKU-A')->getData();
        ReferenceGapScenarios::expectSame(10, $payload['total_stock'], 'Stock stays live');
        ReferenceGapScenarios::expectSame(1, $payload['availability'][0]['warehouse_id'], 'Warehouse id remains present');
    }

    public static function reportPrecision()
    {
        $repository = new ReportFakeRepository();
        $repository->valuation = '10.50';
        $repository->categoryRows = [['category_id' => 1, 'category_name' => 'Category', 'sku_count' => 1, 'total_quantity' => 2, 'total_valuation' => '10.50']];
        $repository->lines['stock_valuation'] = [[
            'total_stock' => 2, 'velocity' => 0, 'reorder_point' => 0,
            'sku' => 'SKU-A', 'name' => 'Alpha', 'unit' => 'pcs',
            'purchase_price' => '3.25', 'valuation' => '6.50',
        ]];
        $service = new ReportService($repository);
        $dashboard = $service->getDashboard($service->normalizeParams(['date_from' => '2026-10-01', 'date_to' => '2026-10-04']), []);
        ReferenceGapScenarios::expectSame(10.5, $dashboard['totalValuation'], 'KPI retains fractional currency');
        ReferenceGapScenarios::expectSame(10.5, $dashboard['categoryData'][0]['valuation'], 'Category retains fractional currency');
        ReferenceGapScenarios::expectSame(3.25, $dashboard['lineItems'][0]['unit_cost'], 'Unit cost retains cents');
        ReferenceGapScenarios::expectSame(6.5, $dashboard['lineItems'][0]['valuation'], 'Line valuation retains cents');
    }

    public static function sharedScopedValuation()
    {
        $stocks = new class extends ProductStockFakeRepository {
            public $calls = [];
            public function totalInventoryValue($categoryId = null, $warehouseId = null)
            {
                $this->calls[] = [$categoryId, $warehouseId];
                $result = new Result();
                $result->code = Result::CODE_SUCCESS;
                $result->info = 'Valuation fixture';
                $result->data = '10.50';

                return $result;
            }
        };
        // Inject a fake aggregate boundary; no PDO connection is constructed.
        $repository = new \App\Repository\MySQL\ReportMySQLRepository(null, $stocks);
        ReferenceGapScenarios::expectSame('10.50', $repository->inventoryValuation(2, 3)->data, 'Report uses shared stock valuation');
        ReferenceGapScenarios::expectSame('10.50', $repository->warehouseValuation(2, 4)->data, 'Warehouse report uses shared valuation');
        ReferenceGapScenarios::expectSame([[2, 3], [2, 4]], $stocks->calls, 'Aggregate scopes are passed unchanged');
    }

    public static function productSortingAndOrderSearch()
    {
        $products = ReferenceGapScenarios::products();
        ReferenceGapScenarios::expectSame([5, 3], array_column($products->findAll(null, 0, 0, null, null, null, null, null, 'name_desc')->data, 'id'), 'Selectable descending sort');
        ReferenceGapScenarios::expectSame([3], array_column($products->findAll(null, 1, 1, null, null, null, null, null, 'sku_desc')->data, 'id'), 'Pagination follows selected sort');
        ReferenceGapScenarios::expectSame([3, 5], array_column($products->findAll(null, 0, 0, null, null, null, null, null, 'invalid SQL')->data, 'id'), 'Invalid sort falls back to allowlist default');
        [$po, $poRepository] = ReferenceGapScenarios::purchaseEnvironment();
        $po->create(ReferenceGapScenarios::purchaseHeader(), [['product_id' => 3, 'qty_ordered' => 1, 'purchase_price' => '3.25']], 1);
        foreach (['#1', 'PO-0001', 'po-0001'] as $number) {
            ReferenceGapScenarios::expectSame(1, count($poRepository->findAll(null, 10, 0, null, 'desc', null, $number)->data), 'PO display number searchable ' . $number);
            ReferenceGapScenarios::expectSame(1, $poRepository->countAll(null, null, null, $number)->data, 'PO count matches search');
        }
        [$so, $soRepository] = ReferenceGapScenarios::salesEnvironment();
        $so->create(ReferenceGapScenarios::salesHeader(), [['product_id' => 3, 'qty' => 1, 'sale_price' => '5.00']], 1);
        foreach (['#1', 'SO-0001', 'so-0001'] as $number) {
            ReferenceGapScenarios::expectSame(1, count($soRepository->findAll(1, ['order_number' => $number])->data), 'SO display number searchable ' . $number);
            ReferenceGapScenarios::expectSame(1, $soRepository->countAll(1, ['order_number' => $number])->data, 'SO count matches search');
        }
    }

    public static function isolatedUnitBoundaries()
    {
        $cache = new InMemoryCacheService();
        $cache->set('permission:Admin', ['products']);
        ReferenceGapScenarios::expectSame(['products'], $cache->get('permission:Admin'), 'Fake cache supports hits');
        $cache->delete('permission:Admin');
        ReferenceGapScenarios::expectSame(null, $cache->get('permission:Admin'), 'Fake cache supports invalidation');
        $unavailable = new InMemoryCacheService(false);
        $unavailable->set('key', 'value');
        ReferenceGapScenarios::expectSame('fallback', $unavailable->get('key', 'fallback'), 'Unavailable cache uses fallback');
        $session = new InMemorySessionManager();
        $user = new \App\Entity\User(1, 'Admin', 'admin@example.com', password_hash('secret', PASSWORD_BCRYPT), 'Admin', true);
        $auth = new \App\Service\AuthService(new \App\Repository\Fake\UserFakeRepository([$user]), $session);
        ReferenceGapScenarios::expectSame(Result::CODE_SUCCESS, $auth->login('admin@example.com', 'secret')->code, 'Login works through in-memory session');
        ReferenceGapScenarios::expectSame(1, $auth->currentUser()->id, 'Session retains authenticated user');
        $auth->logout();
        ReferenceGapScenarios::expectSame(null, $auth->currentUser(), 'Logout clears fake session');
    }

    public static function integrationDatabaseGuard()
    {
        $names = ['TEST_DB_NAME', 'DB_NAME', 'APP_TEST_BASE_URL', 'APP_TEST_DB_NAME'];
        $original = [];
        foreach ($names as $name) {
            $original[$name] = getenv($name);
        }
        try {
            putenv('DB_NAME=inventory_order_management');
            foreach (['', 'inventory_order_management'] as $invalid) {
                putenv('TEST_DB_NAME=' . $invalid);
                $rejected = false;
                try {
                    IntegrationEnvironment::databaseName();
                } catch (\RuntimeException $e) {
                    $rejected = true;
                }
                ReferenceGapScenarios::expectSame(true, $rejected, 'Missing/application DB rejected before connecting');
            }
            putenv('TEST_DB_NAME=ioms_test');
            ReferenceGapScenarios::expectSame('ioms_test', IntegrationEnvironment::databaseName(), 'Dedicated database accepted');
            putenv('APP_TEST_BASE_URL=http://test-app:8080');
            putenv('APP_TEST_DB_NAME=other_database');
            $rejected = false;
            try {
                IntegrationEnvironment::baseUrl();
            } catch (\RuntimeException $e) {
                $rejected = true;
            }
            ReferenceGapScenarios::expectSame(true, $rejected, 'HTTP fixture database mismatch rejected');
        } finally {
            foreach ($original as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        }
    }
    public static function userUpdateContract()
    {
        $oldHash = password_hash('old-secret', PASSWORD_BCRYPT);
        $user = new \App\Entity\User(1, 'Admin', 'admin@example.com', $oldHash, \App\Entity\Role::Admin, true);
        $repository = new \App\Repository\Fake\UserFakeRepository([$user]);
        $service = new \App\Service\UserService($repository);
        $input = ['name' => 'Updated Admin', 'email' => 'admin@example.com', 'role' => 'Sales', 'password' => ''];
        $denied = $service->updateUser(1, $input, 1);
        ReferenceGapScenarios::expectSame(Result::CODE_VALIDATION, $denied->code, 'Self role change rejected');
        ReferenceGapScenarios::expectSame(['role' => 'You cannot change your own role.'], $denied->data, 'Self role validation contract retained');
        ReferenceGapScenarios::expectSame('Admin', $repository->findById(1)->data->role->value, 'Denied update preserves role');
        $input['role'] = 'Admin';
        $updated = $service->updateUser(1, $input, 1);
        ReferenceGapScenarios::expectSame(Result::CODE_SUCCESS, $updated->code, 'Profile update allowed');
        ReferenceGapScenarios::expectSame($oldHash, $updated->data->passwordHash, 'Blank optional password preserves hash');
        $input['password'] = 'new-secret';
        $updated = $service->updateUser(1, $input, 1);
        ReferenceGapScenarios::expectSame(Result::CODE_SUCCESS, $updated->code, 'Password update allowed');
        ReferenceGapScenarios::expectSame(true, password_verify('new-secret', $updated->data->passwordHash), 'New password is hashed and persisted');
    }

    public static function draftStatusRecheck()
    {
        $transaction = new class implements \App\Core\TransactionManagerInterface {
            public $commits = 0;
            public $rollbacks = 0;
            public function beginTransaction() {}
            public function commit() { $this->commits++; }
            public function rollBack() { $this->rollbacks++; }
        };
        $items = new \App\Repository\Fake\SalesOrderItemFakeRepository();
        $repository = new class($items) extends \App\Repository\Fake\SalesOrderFakeRepository {
            public $submitBeforeLock = false;
            public function lockForUpdate($id)
            {
                if ($this->submitBeforeLock) {
                    parent::updateStatus($id, \App\Entity\SalesOrder::STATUS_PENDING_APPROVAL);
                    $this->submitBeforeLock = false;
                }

                return parent::lockForUpdate($id);
            }
        };
        [$service] = ReferenceGapScenarios::salesEnvironment($repository, $items, $transaction);
        $created = $service->create(ReferenceGapScenarios::salesHeader(), [['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00']], 1);
        $commitsBefore = $transaction->commits;
        $repository->submitBeforeLock = true;
        $result = $service->updateDraft($created->data->id, ReferenceGapScenarios::salesHeader('2026-10-03'), [['product_id' => 5, 'qty' => 4, 'sale_price' => '15.00']], 1, false);
        ReferenceGapScenarios::expectSame(Result::CODE_VALIDATION, $result->code, 'Draft update rechecks status under header lock');
        ReferenceGapScenarios::expectSame($commitsBefore, $transaction->commits, 'Stale draft update does not commit');
        ReferenceGapScenarios::expectSame(1, $transaction->rollbacks, 'Stale draft update rolls back');
        $saved = $service->findById($created->data->id);
        ReferenceGapScenarios::expectSame('2026-10-04', $saved->orderDate, 'Stale draft edit preserves header');
        ReferenceGapScenarios::expectSame([3], array_column($saved->items, 'productId'), 'Stale draft edit preserves items');
    }

    public static function productReorderValidation()
    {
        $categories = new CategoryFakeRepository([new \App\Entity\Category(1, 'CAT', 'Category', null, true)]);
        $service = new ProductService(new ProductFakeRepository(), $categories, new ProductStockFakeRepository(), new InMemoryCacheService());
        $input = ['sku' => 'NEW-SKU', 'name' => 'New Product', 'category_id' => 1, 'unit' => 'pcs', 'purchase_price' => '3.25', 'sale_price' => '5.00'];
        foreach (['1.9', '2abc', '-1', 'abc', '4294967296'] as $value) {
            $input['reorder_point'] = $value;
            ReferenceGapScenarios::expectSame(Result::CODE_VALIDATION, $service->createProduct($input, 1)->code, 'Malformed reorder point rejected ' . $value);
        }
        $input['reorder_point'] = '0';
        $created = $service->createProduct($input, 1);
        ReferenceGapScenarios::expectSame(Result::CODE_SUCCESS, $created->code, 'Zero reorder point accepted');
        ReferenceGapScenarios::expectSame(0, $created->data->reorderPoint, 'Zero threshold persisted');
    }

    public static function draftControllerErrors()
    {
        [$service] = ReferenceGapScenarios::salesEnvironment();
        $controller = new class($service) extends \App\Controller\SalesOrderController {
            public function __construct($service)
            {
                $this->currentUser = new \App\Entity\User(1, 'Sales', 'sales@example.com', '', \App\Entity\Role::Sales, true);
                $this->container = new class($service) {
                    private $service;
                    public function __construct($service) { $this->service = $service; }
                    public function getSalesOrderService() { return $this->service; }
                };
            }
            protected function requirePermission($key, $forbiddenMessage = null) { return null; }
            protected function requirePermissionWithCsrf($key, $forbiddenMessage = null) { return null; }
            protected function decodeId($id) { return $id; }
        };
        ReferenceGapScenarios::expectSame(404, $controller->editFormAction(999)->getStatusCode(), 'Missing Draft GET returns 404');
        ReferenceGapScenarios::expectSame(404, $controller->updateAction(999)->getStatusCode(), 'Missing Draft POST returns 404');
        $created = $service->create(ReferenceGapScenarios::salesHeader(), [['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00']], 2);
        ReferenceGapScenarios::expectSame(403, $controller->editFormAction($created->data->id)->getStatusCode(), 'Another Sales user Draft GET forbidden');
        ReferenceGapScenarios::expectSame(403, $controller->updateAction($created->data->id)->getStatusCode(), 'Another Sales user Draft POST forbidden');
    }

    public static function exportDateRanges()
    {
        [$po] = ReferenceGapScenarios::purchaseEnvironment();
        [$so] = ReferenceGapScenarios::salesEnvironment();
        $ledger = new \App\Service\StockLedgerService(new \App\Repository\Fake\StockLedgerFakeRepository());
        foreach ([['2026-02-31', '2026-10-04'], ['2026-10-04', '2026-10-01'], ["2026-10-04\0", '2026-10-05']] as [$from, $to]) {
            foreach ([$po, $so] as $service) {
                $rejected = false;
                try {
                    $service->findForExport($from, $to);
                } catch (\App\Service\Exception\DomainException $e) {
                    $rejected = true;
                }
                ReferenceGapScenarios::expectSame(true, $rejected, 'Order export rejects invalid period');
            }
            ReferenceGapScenarios::expectSame(Result::CODE_VALIDATION, $ledger->findForExport(['date_from' => $from, 'date_to' => $to])->code, 'Ledger export rejects invalid period');
        }
        ReferenceGapScenarios::expectSame([], $po->findForExport('2026-10-01', '2026-10-04'), 'Valid empty order export remains successful');
        ReferenceGapScenarios::expectSame(Result::CODE_SUCCESS, $ledger->findForExport(['date_from' => '2026-10-01', 'date_to' => '2026-10-04'])->code, 'Valid empty ledger export remains successful');
    }

    public static function exportDependencyFailures()
    {
        $poRepository = new class extends \App\Repository\Fake\PurchaseOrderFakeRepository {
            public function findForExport($from, $to, $warehouseId = null)
            {
                $result = new Result();
                $result->code = Result::CODE_INTERNAL;
                $result->info = 'Simulated export failure';
                $result->data = null;

                return $result;
            }
        };
        [$service] = ReferenceGapScenarios::purchaseEnvironment($poRepository);
        $rejected = false;
        try {
            $service->findForExport('2026-10-01', '2026-10-04');
        } catch (\RuntimeException $e) {
            $rejected = true;
            ReferenceGapScenarios::expectSame(Result::MESSAGE_FAILED_FUNCTION, $e->getMessage(), 'Export failure is sanitized');
        }
        ReferenceGapScenarios::expectSame(true, $rejected, 'Failed order export does not return empty success');
        $ledgerRepository = new class extends \App\Repository\Fake\StockLedgerFakeRepository {
            public function findForExport($from, $to, $warehouseId = null, $categoryId = null, $search = '')
            {
                $result = new Result();
                $result->code = Result::CODE_INTERNAL;
                $result->info = 'Simulated ledger failure';
                $result->data = null;

                return $result;
            }
        };
        $ledger = new \App\Service\StockLedgerService($ledgerRepository);
        ReferenceGapScenarios::expectSame(Result::CODE_INTERNAL, $ledger->findForExport(['date_from' => '2026-10-01', 'date_to' => '2026-10-04'])->code, 'Failed ledger export retains internal failure');
    }

}
