<?php

namespace App\Entity;

use DateTimeImmutable;

class SalesOrderItem
{
    public $id;
    public $salesOrderId;
    public $productId;
    public $qty;
    public $salePrice;
    public $createdAt;
    // Joined fields, populated by list/detail queries only
    public $productName;
    public $productSku;
    public $productUnit;

    public function __construct(
        $id,
        $salesOrderId,
        $productId,
        $qty,
        $salePrice,
        $createdAt = null,
        $productName = null,
        $productSku = null,
        $productUnit = null
    ) {
        $this->id = $id;
        $this->salesOrderId = $salesOrderId;
        $this->productId = $productId;
        $this->qty = $qty;
        $this->salePrice = $salePrice;
        $this->createdAt = $createdAt;
        $this->productName = $productName;
        $this->productSku = $productSku;
        $this->productUnit = $productUnit;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['sales_order_id'],
            (int) $row['product_id'],
            (int) $row['qty'],
            (string) $row['sale_price'],
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            $row['product_name'] ?? null,
            $row['product_sku'] ?? null,
            $row['product_unit'] ?? null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->salesOrderId,
            'product_id' => $this->productId,
            'qty' => $this->qty,
            'sale_price' => $this->salePrice,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
