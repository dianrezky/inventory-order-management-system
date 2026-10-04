<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\PurchaseOrderItem;
use App\Repository\Interface\PurchaseOrderItemRepositoryInterface;
use DateTimeImmutable;

// In-memory fake backing the unit test suite; no database dependency.
class PurchaseOrderItemFakeRepository implements PurchaseOrderItemRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($items = [])
    {
        foreach ($items as $item) {
            $this->byId[$item->id] = $item;
            $this->nextId = max($this->nextId, $item->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find purchase order item';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    // Fake: no real row lock.
    public function findByIdForUpdate($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to lock purchase order item';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findByPurchaseOrderId($purchaseOrderId)
    {
        $items = array_values(array_filter(
            $this->byId,
            function ($i) use ($purchaseOrderId) { return $i->purchaseOrderId === $purchaseOrderId; }
        ));

        usort($items, function ($a, $b) { return $a->id <=> $b->id; });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list purchase order items';
        $result->data = $items;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new PurchaseOrderItem(
            $id,
            (int) $data['purchase_order_id'],
            (int) $data['product_id'],
            (int) $data['qty_ordered'],
            0,
            (string) $data['purchase_price'],
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create purchase order item';
        $result->data = $id;

        return $result;
    }

    public function incrementQtyReceived($id, $qty)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new PurchaseOrderItem(
            $existing->id,
            $existing->purchaseOrderId,
            $existing->productId,
            $existing->qtyOrdered,
            $existing->qtyReceived + $qty,
            $existing->purchasePrice,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update purchase order item received quantity';
        $result->data = null;

        return $result;
    }
}
