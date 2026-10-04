<?php

namespace App\Entity;

use DateTimeImmutable;

class PurchaseOrderItem
{
    public $id;
    public $purchaseOrderId;
    public $productId;
    public $qtyOrdered;
    public $qtyReceived;
    public $purchasePrice;
    public $createdAt;
    public $updatedAt;
    // Optional joined fields, populated by list/detail queries only
    public $productName;
    public $productSku;
    public $productUnit;

    public function __construct(
        $id,
        $purchaseOrderId,
        $productId,
        $qtyOrdered,
        $qtyReceived,
        $purchasePrice,
        $createdAt = null,
        $updatedAt = null,
        $productName = null,
        $productSku = null,
        $productUnit = null
    ) {
        $this->id = $id;
        $this->purchaseOrderId = $purchaseOrderId;
        $this->productId = $productId;
        $this->qtyOrdered = $qtyOrdered;
        $this->qtyReceived = $qtyReceived;
        $this->purchasePrice = $purchasePrice;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->productName = $productName;
        $this->productSku = $productSku;
        $this->productUnit = $productUnit;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['purchase_order_id'],
            (int) $row['product_id'],
            (int) $row['qty_ordered'],
            (int) $row['qty_received'],
            (string) $row['purchase_price'],
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
            isset($row['product_name']) ? (string) $row['product_name'] : null,
            isset($row['product_sku']) ? (string) $row['product_sku'] : null,
            isset($row['product_unit']) ? (string) $row['product_unit'] : null
        );
    }

    public function qtyRemaining()
    {
        return max(0, $this->qtyOrdered - $this->qtyReceived);
    }

    public function isFullyReceived()
    {
        return $this->qtyReceived >= $this->qtyOrdered;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchaseOrderId,
            'product_id' => $this->productId,
            'qty_ordered' => $this->qtyOrdered,
            'qty_received' => $this->qtyReceived,
            'purchase_price' => $this->purchasePrice,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
