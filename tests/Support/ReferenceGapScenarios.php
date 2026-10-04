<?php

namespace Tests\Support;

use App\Core\FakeTransactionManager;
use App\Core\Result;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ProductStock;
use App\Entity\PurchaseOrder;
use App\Entity\SalesOrder;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Repository\Fake\CustomerFakeRepository;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\ProductStockFakeRepository;
use App\Repository\Fake\PurchaseOrderFakeRepository;
use App\Repository\Fake\PurchaseOrderItemFakeRepository;
use App\Repository\Fake\SalesOrderFakeRepository;
use App\Repository\Fake\SalesOrderItemFakeRepository;
use App\Repository\Fake\StockLedgerFakeRepository;
use App\Repository\Fake\SupplierFakeRepository;
use App\Repository\Fake\WarehouseFakeRepository;
use App\Service\GoodsIssueService;
use App\Service\GoodsReceiptService;
use App\Service\PurchaseOrderService;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;

// Public service regressions share the same fixtures between CLI smoke and PHPUnit.
final class ReferenceGapScenarios
{
    public static $assertionCount = 0;
    public static function products()
    {
        return new ProductFakeRepository([
            new Product(5, 'SKU-Z', 'Zulu', 1, 'pcs', '10.50', '15.00', 0, null, true),
            new Product(3, 'SKU-A', 'Alpha', 1, 'pcs', '3.25', '5.00', 0, null, true),
        ]);
    }

    public static function purchaseEnvironment($repository = null)
    {
        $repository ??= new PurchaseOrderFakeRepository();
        $items = new PurchaseOrderItemFakeRepository();
        $service = new PurchaseOrderService($repository, $items,
            new SupplierFakeRepository([new Supplier(1, 'Supplier', null, null, null, null, true)]),
            new WarehouseFakeRepository([new Warehouse(1, 'WH-1', 'Main', null, true)]), self::products());

        return [$service, $repository, $items];
    }

    public static function salesEnvironment($repository = null, $items = null, $transaction = null)
    {
        $items ??= new SalesOrderItemFakeRepository();
        $repository ??= new SalesOrderFakeRepository($items);
        $service = new SalesOrderService($repository, $items,
            new CustomerFakeRepository([new Customer(1, 'Customer', null, null, null, null, true)]),
            new WarehouseFakeRepository([new Warehouse(1, 'WH-1', 'Main', null, true)]),
            self::products(), new SalesOrderPolicy(), null, $transaction);

        return [$service, $repository, $items];
    }

    public static function purchaseHeader($date = '2026-10-04')
    {
        return ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => $date];
    }

    public static function salesHeader($date = '2026-10-04')
    {
        return ['customer_id' => 1, 'source_warehouse_id' => 1, 'order_date' => $date];
    }

    public static function expectSame($expected, $actual, $description)
    {
        self::$assertionCount++;
        if ($expected !== $actual) {
            throw new \RuntimeException($description . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
        }
    }

    public static function malformedOrderInputs()
    {
        [$po] = self::purchaseEnvironment();
        [$so] = self::salesEnvironment();
        foreach (['1.9', '2abc', '-0.5', 'abc', '0', '-1', '4294967296', '99999999999999999999999999'] as $quantity) {
            $poResult = $po->create(self::purchaseHeader(), [['product_id' => 3, 'qty_ordered' => $quantity, 'purchase_price' => '3.25']], 1);
            self::expectSame(Result::CODE_VALIDATION, $poResult->code, 'PO rejects malformed quantity ' . $quantity);
            $soResult = $so->create(self::salesHeader(), [['product_id' => 3, 'qty' => $quantity, 'sale_price' => '5.00']], 1);
            self::expectSame(Result::CODE_VALIDATION, $soResult->code, 'SO rejects malformed quantity ' . $quantity);
        }
        foreach (['1e999', '10000000000000', '-0.01', '2abc'] as $price) {
            self::expectSame(Result::CODE_VALIDATION, $po->create(self::purchaseHeader(), [['product_id' => 3, 'qty_ordered' => 2, 'purchase_price' => $price]], 1)->code, 'PO rejects out-of-range price ' . $price);
            self::expectSame(Result::CODE_VALIDATION, $so->create(self::salesHeader(), [['product_id' => 3, 'qty' => 2, 'sale_price' => $price]], 1)->code, 'SO rejects out-of-range price ' . $price);
        }
        foreach (['2026-02-31', '2026-13-01', '2026-2-01', '2026-10-04suffix', "2026-10-04\0", ''] as $date) {
            self::expectSame(Result::CODE_VALIDATION, $po->create(self::purchaseHeader($date), [['product_id' => 3, 'qty_ordered' => 2, 'purchase_price' => '3.25']], 1)->code, 'PO rejects nonexistent date ' . $date);
            self::expectSame(Result::CODE_VALIDATION, $so->create(self::salesHeader($date), [['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00']], 1)->code, 'SO rejects nonexistent date ' . $date);
        }
        self::expectSame(Result::CODE_VALIDATION, $po->create(self::purchaseHeader(), [['product_id' => 'abc', 'qty_ordered' => '', 'purchase_price' => '3.25']], 1)->code, 'Malformed product id is not silently skipped as a blank row');
        self::expectSame(Result::CODE_VALIDATION, $so->create(self::salesHeader(), [['product_id' => 'abc', 'qty' => '', 'sale_price' => '5.00']], 1)->code, 'SO malformed product id is not a blank row');
        $valid = $po->create(self::purchaseHeader('2028-02-29'), [['product_id' => 3, 'qty_ordered' => '2', 'purchase_price' => '3.25']], 1);
        self::expectSame(Result::CODE_SUCCESS, $valid->code, 'Real leap date and integer string accepted');
        self::expectSame(2, $valid->data->items[0]->qtyOrdered, 'Valid quantity persisted unchanged');
    }

    public static function purchaseCancellationRace()
    {
        $repository = new class extends PurchaseOrderFakeRepository {
            public $receiptAfterNextRead = false;
            public function findById($id)
            {
                $snapshot = parent::findById($id);
                if ($this->receiptAfterNextRead) {
                    $this->receiptAfterNextRead = false;
                    parent::updateStatus($id, PurchaseOrder::STATUS_RECEIVED);
                }

                return $snapshot;
            }
        };
        [$service] = self::purchaseEnvironment($repository);
        $created = $service->create(self::purchaseHeader(), [['product_id' => 3, 'qty_ordered' => 2, 'purchase_price' => '3.25']], 1);
        self::expectSame(Result::CODE_SUCCESS, $service->submit($created->data->id)->code, 'PO submitted');
        $repository->receiptAfterNextRead = true;
        $cancelled = $service->cancel($created->data->id, 1);
        self::expectSame(Result::CODE_VALIDATION, $cancelled->code, 'Stale cancellation rejected');
        self::expectSame(PurchaseOrder::STATUS_RECEIVED, $repository->findById($created->data->id)->data->status, 'Received status preserved');
    }

    public static function salesDecisionRaces()
    {
        $items = new SalesOrderItemFakeRepository();
        $repository = new class($items) extends SalesOrderFakeRepository {
            public $statusAfterNextRead = null;
            public function findById($id)
            {
                $snapshot = parent::findById($id);
                if ($this->statusAfterNextRead !== null) {
                    $status = $this->statusAfterNextRead;
                    $this->statusAfterNextRead = null;
                    parent::updateStatus($id, $status);
                }

                return $snapshot;
            }
        };
        [$service] = self::salesEnvironment($repository, $items);
        $created = $service->create(self::salesHeader(), [['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00']], 1);
        $id = $created->data->id;
        self::expectSame(Result::CODE_SUCCESS, $service->submitForApproval($id, 1)->code, 'SO submitted');
        $repository->statusAfterNextRead = SalesOrder::STATUS_CANCELLED;
        self::expectSame(Result::CODE_VALIDATION, $service->approve($id, 2, true)->code, 'Approval does not overwrite concurrent rejection');
        self::expectSame(SalesOrder::STATUS_CANCELLED, $repository->findById($id)->data->status, 'Concurrent rejection preserved');
        $repository->updateStatus($id, SalesOrder::STATUS_APPROVED);
        $repository->statusAfterNextRead = SalesOrder::STATUS_FULFILLED;
        self::expectSame(Result::CODE_VALIDATION, $service->cancel($id, 2, true)->code, 'Cancellation does not overwrite concurrent issue');
        self::expectSame(SalesOrder::STATUS_FULFILLED, $repository->findById($id)->data->status, 'Fulfilled status preserved');
    }

    public static function stockRecorder($quantity)
    {
        return new class([new ProductStock(1, 5, 1, $quantity), new ProductStock(2, 3, 1, $quantity)]) extends ProductStockFakeRepository {
            public $lockedProductIds = [];
            public function lockForUpdate($productId, $warehouseId)
            {
                $this->lockedProductIds[] = $productId;

                return parent::lockForUpdate($productId, $warehouseId);
            }
        };
    }

    public static function receiptLockOrder()
    {
        [$po, $repository, $items] = self::purchaseEnvironment();
        $stocks = self::stockRecorder(0);
        $ledger = new StockLedgerFakeRepository();
        $receipt = new GoodsReceiptService(new FakeTransactionManager(), $repository, $items, $stocks, $ledger, $po);
        $created = $po->create(self::purchaseHeader(), [
            ['product_id' => 5, 'qty_ordered' => 4, 'purchase_price' => '10.50'],
            ['product_id' => 3, 'qty_ordered' => 2, 'purchase_price' => '3.25'],
        ], 1);
        $submitted = $po->submit($created->data->id);
        self::expectSame([5, 3], array_column($submitted->data->items, 'productId'), 'Receipt fixture really returns reverse product order');
        $lines = [$submitted->data->items[0]->id => 4, $submitted->data->items[1]->id => 2];
        $result = $receipt->process($created->data->id, $lines, 1);
        self::expectSame(Result::CODE_SUCCESS, $result->code, 'Receipt completes');
        self::expectSame([3, 5], $stocks->lockedProductIds, 'Receipt locks ascending product ids');
        self::expectSame(PurchaseOrder::STATUS_RECEIVED, $result->data->status, 'Receipt final status');
        self::expectSame(4, $stocks->find(5, 1)->data->quantity, 'Receipt preserves line/product mapping');
        self::expectSame(2, $ledger->sumByProductWarehouse(3, 1)->data, 'Receipt ledger preserves quantity');
    }

    public static function issueLockOrder()
    {
        [$so, $repository, $items] = self::salesEnvironment();
        $stocks = self::stockRecorder(10);
        $ledger = new StockLedgerFakeRepository();
        $created = $so->create(self::salesHeader(), [
            ['product_id' => 5, 'qty' => 4, 'sale_price' => '15.00'],
            ['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00'],
        ], 1);
        $id = $created->data->id;
        self::expectSame([5, 3], array_column($items->findBySalesOrderId($id)->data, 'productId'), 'Issue fixture really returns reverse product order');
        $so->submitForApproval($id, 1);
        $so->approve($id, 2, true);
        $issue = new GoodsIssueService(new FakeTransactionManager(), $repository, $items, $stocks, $ledger, new SalesOrderPolicy());
        $result = $issue->issue($id, 2);
        self::expectSame(Result::CODE_SUCCESS, $result->code, 'Issue completes');
        self::expectSame([3, 5], $stocks->lockedProductIds, 'Issue locks ascending product ids');
        self::expectSame(SalesOrder::STATUS_FULFILLED, $result->data->status, 'Issue final status');
        self::expectSame(6, $stocks->find(5, 1)->data->quantity, 'Issue preserves line/product mapping');
        self::expectSame(-2, $ledger->sumByProductWarehouse(3, 1)->data, 'Issue ledger preserves quantity');
    }

    public static function malformedReceipt()
    {
        [$po, $repository, $items] = self::purchaseEnvironment();
        $created = $po->create(self::purchaseHeader(), [['product_id' => 3, 'qty_ordered' => 4, 'purchase_price' => '3.25']], 1);
        $submitted = $po->submit($created->data->id);
        $stocks = self::stockRecorder(0);
        $ledger = new StockLedgerFakeRepository();
        $receipt = new GoodsReceiptService(new FakeTransactionManager(), $repository, $items, $stocks, $ledger, $po);
        foreach (['1.9', '2abc', '-1', 'abc'] as $quantity) {
            self::expectSame(Result::CODE_VALIDATION, $receipt->process($created->data->id, [$submitted->data->items[0]->id => $quantity], 1)->code, 'Receipt rejects malformed quantity ' . $quantity);
        }
        self::expectSame([], $stocks->lockedProductIds, 'Malformed receipts do not reach stock locks');
        self::expectSame(0, $ledger->sumByProductWarehouse(3, 1)->data, 'Malformed receipts do not write ledger');
    }

    public static function draftSalesEdit()
    {
        [$service, $repository, $items] = self::salesEnvironment();
        $created = $service->create(self::salesHeader(), [['product_id' => 3, 'qty' => 2, 'sale_price' => '5.00']], 1);
        $id = $created->data->id;
        self::expectSame(Result::CODE_VALIDATION, $service->updateDraft($id, self::salesHeader(), [['product_id' => 5, 'qty' => 4, 'sale_price' => '15.00']], 2, false)->code, 'Another Sales user cannot edit');
        self::expectSame(3, $items->findBySalesOrderId($id)->data[0]->productId, 'Denied edit does not replace items');
        self::expectSame(Result::CODE_SUCCESS, $service->updateDraft($id, self::salesHeader('2026-10-03'), [['product_id' => 5, 'qty' => 4, 'sale_price' => '15.00']], 1, false)->code, 'Creator can edit Draft');
        $saved = $service->findById($id);
        self::expectSame('2026-10-03', $saved->orderDate, 'Edit updates header');
        self::expectSame([5], array_column($saved->items, 'productId'), 'Edit replaces line set');
        self::expectSame(4, $saved->items[0]->qty, 'Edit updates quantity');
        self::expectSame(Result::CODE_SUCCESS, $service->updateDraft($id, self::salesHeader(), [['product_id' => 3, 'qty' => 1, 'sale_price' => '5.00']], 2, true)->code, 'Admin can edit Draft');
        $service->submitForApproval($id, 1);
        self::expectSame(Result::CODE_VALIDATION, $service->updateDraft($id, self::salesHeader(), [['product_id' => 5, 'qty' => 9, 'sale_price' => '15.00']], 1, false)->code, 'Submitted order cannot be edited');
    }
}
