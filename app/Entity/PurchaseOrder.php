<?php

namespace App\Entity;

use DateTimeImmutable;

class PurchaseOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'PartiallyReceived';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';

    public $id;
    public $supplierId;
    public $destinationWarehouseId;
    public $status;
    public $orderDate;
    public $note;
    public $createdBy;
    public $createdAt;
    public $updatedAt;
    // Optional joined fields, populated by list/detail queries only
    public $supplierName;
    public $destinationWarehouseName;
    public $createdByName;
    public $items;
    // List-view aggregates (populated by findAll() when joined with purchase_order_items)
    public $itemsCount = 0;
    public $itemsQtyOrdered = 0;
    public $itemsQtyReceived = 0;
    public $totalValue = '0';
    public $receiptProgressPct = 0;

    public function __construct(
        $id,
        $supplierId,
        $destinationWarehouseId,
        $status,
        $orderDate,
        $note,
        $createdBy,
        $createdAt = null,
        $updatedAt = null,
        $supplierName = null,
        $destinationWarehouseName = null,
        $createdByName = null,
        $items = []
    ) {
        $this->id = $id;
        $this->supplierId = $supplierId;
        $this->destinationWarehouseId = $destinationWarehouseId;
        $this->status = $status;
        $this->orderDate = $orderDate;
        $this->note = $note;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->supplierName = $supplierName;
        $this->destinationWarehouseName = $destinationWarehouseName;
        $this->createdByName = $createdByName;
        $this->items = $items;
    }

    public static function fromArray($row)
    {
        $po = new self(
            (int) $row['id'],
            (int) $row['supplier_id'],
            (int) $row['destination_warehouse_id'],
            (string) $row['status'],
            (string) $row['order_date'],
            isset($row['note']) && $row['note'] !== null ? (string) $row['note'] : null,
            (int) $row['created_by'],
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
            isset($row['supplier_name']) ? (string) $row['supplier_name'] : null,
            isset($row['destination_warehouse_name']) ? (string) $row['destination_warehouse_name'] : null,
            isset($row['created_by_name']) ? (string) $row['created_by_name'] : null
        );

        // Aggregates populated when list query joins purchase_order_items (GROUP BY po.id)
        $po->itemsCount       = isset($row['items_count'])       ? (int) $row['items_count']       : 0;
        $po->itemsQtyOrdered  = isset($row['items_qty_ordered']) ? (int) $row['items_qty_ordered'] : 0;
        $po->itemsQtyReceived = isset($row['items_qty_received'])? (int) $row['items_qty_received']: 0;
        $po->totalValue       = isset($row['total_value'])       ? (string) $row['total_value']    : '0';

        $qtyOrdered  = $po->itemsQtyOrdered;
        $qtyReceived = $po->itemsQtyReceived;
        $po->receiptProgressPct = $qtyOrdered > 0 ? (int) round(($qtyReceived / $qtyOrdered) * 100) : 0;

        return $po;
    }

    public function withItems($items)
    {
        return new self(
            $this->id,
            $this->supplierId,
            $this->destinationWarehouseId,
            $this->status,
            $this->orderDate,
            $this->note,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
            $this->supplierName,
            $this->destinationWarehouseName,
            $this->createdByName,
            $items
        );
    }

    public function canBeCancelled()
    {
        return in_array(
            $this->status,
            [self::STATUS_DRAFT, self::STATUS_ORDERED, self::STATUS_PARTIALLY_RECEIVED],
            true
        );
    }

    public function canReceiveGoods()
    {
        return in_array($this->status, [self::STATUS_ORDERED, self::STATUS_PARTIALLY_RECEIVED], true);
    }

    // Display name for the UI. The label belongs to the order itself, so views
    // never have to look it up in a message table.
    public function statusLabel()
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor($status)
    {
        if ($status === self::STATUS_PARTIALLY_RECEIVED) {
            return 'Partially Received';
        }

        return (string) $status;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplierId,
            'destination_warehouse_id' => $this->destinationWarehouseId,
            'status' => $this->status,
            'order_date' => $this->orderDate,
            'note' => $this->note,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
