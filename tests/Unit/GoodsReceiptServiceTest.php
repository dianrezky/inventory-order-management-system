<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Result;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\ProductStockFakeRepository;
use App\Repository\Fake\PurchaseOrderFakeRepository;
use App\Repository\Fake\PurchaseOrderItemFakeRepository;
use App\Repository\Fake\StockLedgerFakeRepository;
use App\Repository\Fake\SupplierFakeRepository;
use App\Repository\Fake\WarehouseFakeRepository;
use App\Service\GoodsReceiptService;
use App\Service\PurchaseOrderService;
use PHPUnit\Framework\TestCase;

final class GoodsReceiptServiceTest extends TestCase
{
    private function makeServices(): array
    {
        $supplierRepo = new SupplierFakeRepository([new Supplier(1, 'Acme', null, null, null, null, true)]);
        $warehouseRepo = new WarehouseFakeRepository([new Warehouse(1, 'WH-1', 'Main', null, true)]);
        $productRepo = new ProductFakeRepository([
            new Product(1, 'SKU-1', 'Widget', 1, 'pcs', '10.00', '15.00', 5, null, true),
        ]);
        $poRepo = new PurchaseOrderFakeRepository();
        $poItemRepo = new PurchaseOrderItemFakeRepository();
        $stockRepo = new ProductStockFakeRepository();
        $ledgerRepo = new StockLedgerFakeRepository();

        $poService = new PurchaseOrderService($poRepo, $poItemRepo, $supplierRepo, $warehouseRepo, $productRepo);
        $receiptService = new GoodsReceiptService(
            new \App\Core\FakeTransactionManager(),
            $poRepo,
            $poItemRepo,
            $stockRepo,
            $ledgerRepo,
            $poService,
        );

        return [$receiptService, $poService, $stockRepo, $ledgerRepo, $poRepo];
    }

    public function testFullReceiptMarksPoReceivedAndIncrementsStock(): void
    {
        [$receiptService, $poService, $stockRepo, $ledgerRepo] = $this->makeServices();

        $poResult = $poService->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 100, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(0, $poResult->code);
        $po = $poResult->data;

        $submitResult = $poService->submit($po->id);
        self::assertSame(0, $submitResult->code);
        $submitted = $submitResult->data;
        $itemId = $submitted->items[0]->id;

        $result = $receiptService->process($po->id, [$itemId => 100], userId: 1);

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $result->data->status);
        self::assertSame(100, $result->data->items[0]->qtyReceived);

        $stock = $stockRepo->find(1, 1)->data;
        self::assertNotNull($stock);
        self::assertSame(100, $stock->quantity);
        self::assertSame(100, $ledgerRepo->sumByProductWarehouse(1, 1)->data);
    }

    public function testPartialReceiptTwiceReachesReceivedAndInvariantHolds(): void
    {
        [$receiptService, $poService, $stockRepo, $ledgerRepo] = $this->makeServices();

        $poResult = $poService->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 100, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(0, $poResult->code);
        $po = $poResult->data;

        $submitResult = $poService->submit($po->id);
        self::assertSame(0, $submitResult->code);
        $submitted = $submitResult->data;
        $itemId = $submitted->items[0]->id;

        $afterFirst = $receiptService->process($po->id, [$itemId => 60], userId: 1);
        self::assertSame(0, $afterFirst->code);
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $afterFirst->data->status);
        self::assertSame(60, $afterFirst->data->items[0]->qtyReceived);

        $afterSecond = $receiptService->process($po->id, [$itemId => 40], userId: 1);
        self::assertSame(0, $afterSecond->code);
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $afterSecond->data->status);
        self::assertSame(100, $afterSecond->data->items[0]->qtyReceived);

        $stock = $stockRepo->find(1, 1)->data;
        self::assertNotNull($stock);
        self::assertSame($ledgerRepo->sumByProductWarehouse(1, 1)->data, $stock->quantity);
        self::assertSame(100, $stock->quantity);
    }

    public function testReceiptRejectsQtyExceedingRemaining(): void
    {
        [$receiptService, $poService] = $this->makeServices();

        $poResult = $poService->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 100, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(0, $poResult->code);
        $po = $poResult->data;

        $submitResult = $poService->submit($po->id);
        self::assertSame(0, $submitResult->code);
        $submitted = $submitResult->data;
        $itemId = $submitted->items[0]->id;

        $result = $receiptService->process($po->id, [$itemId => 150], userId: 1);

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testReceiptRejectedWhenPoNotOrderedOrPartiallyReceived(): void
    {
        [$receiptService, $poService] = $this->makeServices();

        $poResult = $poService->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 100, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(0, $poResult->code);
        $po = $poResult->data;
        $itemId = $po->items[0]->id;

        $result = $receiptService->process($po->id, [$itemId => 10], userId: 1);

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
    }

    public function testMixedBatchWithOneInvalidLineRejectsWholeBatch(): void
    {
        [$receiptService, $poService, $stockRepo, $ledgerRepo] = $this->makeServices();

        $poResult = $poService->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 50, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(0, $poResult->code);
        $po = $poResult->data;

        $submitResult = $poService->submit($po->id);
        self::assertSame(0, $submitResult->code);
        $submitted = $submitResult->data;
        $itemId = $submitted->items[0]->id;

        $result = $receiptService->process($po->id, [$itemId => 999], userId: 1);

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);

        self::assertNull($stockRepo->find(1, 1)->data);
        self::assertSame(0, $ledgerRepo->sumByProductWarehouse(1, 1)->data);

        $refreshed = $poService->findById($po->id);
        self::assertNotNull($refreshed);
        self::assertSame(0, $refreshed->items[0]->qtyReceived);
    }
}
