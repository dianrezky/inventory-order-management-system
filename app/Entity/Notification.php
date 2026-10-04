<?php

namespace App\Entity;

use DateTimeImmutable;

class Notification
{
    public const TYPE_LOW_STOCK = 'low_stock';

    public $id;
    public $type;
    public $message;
    public $productId;
    public $warehouseId;
    public $productSku;
    public $productName;
    public $warehouseName;
    public $readAt;
    public $createdAt;

    public function __construct(
        $id,
        $type,
        $message,
        $productId,
        $warehouseId,
        $productSku,
        $productName,
        $warehouseName,
        $readAt,
        $createdAt
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->message = $message;
        $this->productId = $productId;
        $this->warehouseId = $warehouseId;
        $this->productSku = $productSku;
        $this->productName = $productName;
        $this->warehouseName = $warehouseName;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (string) $row['type'],
            (string) $row['message'],
            isset($row['product_id']) && $row['product_id'] !== null ? (int) $row['product_id'] : null,
            isset($row['warehouse_id']) && $row['warehouse_id'] !== null ? (int) $row['warehouse_id'] : null,
            $row['product_sku'] ?? null,
            $row['product_name'] ?? null,
            $row['warehouse_name'] ?? null,
            isset($row['read_at']) && $row['read_at'] !== null ? new DateTimeImmutable((string) $row['read_at']) : null,
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->message,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'product_sku' => $this->productSku,
            'product_name' => $this->productName,
            'warehouse_name' => $this->warehouseName,
            'read_at' => $this->readAt ? $this->readAt->format('Y-m-d H:i:s') : null,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
        ];
    }

    public function isRead()
    {
        return $this->readAt !== null;
    }
}
