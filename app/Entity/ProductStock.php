<?php

namespace App\Entity;

class ProductStock
{
    public $id;
    public $productId;
    public $warehouseId;
    public $quantity;
    public $updatedAt;

    public function __construct($id, $productId, $warehouseId, $quantity, $updatedAt = null)
    {
        $this->id = $id;
        $this->productId = $productId;
        $this->warehouseId = $warehouseId;
        $this->quantity = $quantity;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['product_id'],
            (int) $row['warehouse_id'],
            (int) $row['quantity'],
            isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at']) : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => $this->quantity,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
