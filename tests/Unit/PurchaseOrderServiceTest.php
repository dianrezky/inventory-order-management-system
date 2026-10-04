<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Result;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\PurchaseOrderFakeRepository;
use App\Repository\Fake\PurchaseOrderItemFakeRepository;
use App\Repository\Fake\SupplierFakeRepository;
use App\Repository\Fake\WarehouseFakeRepository;
use App\Service\PurchaseOrderService;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderServiceTest extends TestCase
{
    private function makeService(): array
    {
        $supplierRepo = new SupplierFakeRepository([
            new Supplier(1, 'Acme Supplier', null, null, null, null, true),
        ]);
        $warehouseRepo = new WarehouseFakeRepository([
            new Warehouse(1, 'WH-1', 'Main Warehouse', null, true),
        ]);
        $productRepo = new ProductFakeRepository([
            new Product(1, 'SKU-1', 'Widget', 1, 'pcs', '10.00', '15.00', 5, null, true),
        ]);
        $poRepo = new PurchaseOrderFakeRepository();
        $poItemRepo = new PurchaseOrderItemFakeRepository();

        $service = new PurchaseOrderService($poRepo, $poItemRepo, $supplierRepo, $warehouseRepo, $productRepo);

        return [$service, $poRepo, $poItemRepo];
    }

    public function testCreateStartsInDraftWithItems(): void
    {
        [$service] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15', 'note' => 'Test'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame(PurchaseOrder::STATUS_DRAFT, $result->data->status);
        self::assertCount(1, $result->data->items);
        self::assertSame(10, $result->data->items[0]->qtyOrdered);
    }

    public function testCreateRejectsEmptyItemList(): void
    {
        [$service] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [],
            createdByUserId: 1,
        );

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame('A purchase order needs at least one line item.', $result->info);
    }

    public function testSubmitTransitionsDraftToOrdered(): void
    {
        [$service] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $submitted = $service->submit($result->data->id);
        self::assertSame(0, $submitted->code);
        self::assertSame(PurchaseOrder::STATUS_ORDERED, $submitted->data->status);
    }

    public function testSubmitRejectsNonDraftState(): void
    {
        [$service] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $submitResult = $service->submit($result->data->id);
        self::assertSame(0, $submitResult->code);

        $second = $service->submit($result->data->id);
        self::assertNotSame(0, $second->code);
        self::assertSame('Only a Draft purchase order can be submitted.', $second->info);
    }

    public function testCancelAllowedFromDraftAndOrdered(): void
    {
        [$service] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $cancelled = $service->cancel($result->data->id);
        self::assertSame(0, $cancelled->code);
        self::assertSame(PurchaseOrder::STATUS_CANCELLED, $cancelled->data->status);
    }

    public function testCancelAllowedAfterPartialReceipt(): void
    {
        // Brief §2.3/B-26: a PO may be cancelled at any stage before Received,
        // including PartiallyReceived — already-received stock is not reversed,
        // only the remaining outstanding quantity is abandoned.
        [$service, $poRepo] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $submitResult = $service->submit($result->data->id);
        self::assertSame(0, $submitResult->code);

        $poRepo->updateStatus($result->data->id, PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

        $cancelResult = $service->cancel($result->data->id);
        self::assertSame(0, $cancelResult->code);
        self::assertSame(PurchaseOrder::STATUS_CANCELLED, $cancelResult->data->status);
    }

    public function testCancelRejectedAfterFullyReceived(): void
    {
        [$service, $poRepo] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $submitResult = $service->submit($result->data->id);
        self::assertSame(0, $submitResult->code);

        $poRepo->updateStatus($result->data->id, PurchaseOrder::STATUS_RECEIVED);

        $cancelResult = $service->cancel($result->data->id);
        self::assertNotSame(0, $cancelResult->code);
        self::assertSame(
            'This purchase order can no longer be cancelled — it has already been fully received or is already cancelled.',
            $cancelResult->info
        );
    }

    public function testRecomputeStatusBecomesPartiallyReceivedThenReceived(): void
    {
        [$service, , $poItemRepo] = $this->makeService();

        $result = $service->create(
            ['supplier_id' => 1, 'destination_warehouse_id' => 1, 'order_date' => '2026-01-15'],
            [['product_id' => 1, 'qty_ordered' => 100, 'purchase_price' => '10.00']],
            createdByUserId: 1,
        );
        self::assertSame(Result::CODE_SUCCESS, $result->code);

        $submitResult = $service->submit($result->data->id);
        self::assertSame(0, $submitResult->code);

        $itemId = $result->data->items[0]->id;
        $poItemRepo->incrementQtyReceived($itemId, 60);

        $statusResult = $service->recomputeStatus($result->data->id);
        self::assertSame(0, $statusResult->code);
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $statusResult->data);

        $poItemRepo->incrementQtyReceived($itemId, 40);

        $finalResult = $service->recomputeStatus($result->data->id);
        self::assertSame(0, $finalResult->code);
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $finalResult->data);
    }
}
