<?php

namespace App\Entity;

use DateTimeImmutable;

class Product
{
    public $id;
    public $sku;
    public $barcode;
    public $name;
    public $description;
    public $categoryId;
    public $unit;
    public $purchasePrice;
    public $salePrice;
    public $reorderPoint;
    public $imagePath;
    public $isActive;
    public $createdAt;
    public $updatedAt;
    // Optional joined fields, populated by list/detail queries only
    public $categoryName;

    public function __construct(
        $id,
        $sku,
        $name,
        $categoryId,
        $unit,
        $purchasePrice,
        $salePrice,
        $reorderPoint,
        $imagePath,
        $isActive,
        $createdAt = null,
        $updatedAt = null,
        $categoryName = null,
        $barcode = null,
        $description = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->name = $name;
        $this->categoryId = $categoryId;
        $this->unit = $unit;
        $this->purchasePrice = $purchasePrice;
        $this->salePrice = $salePrice;
        $this->reorderPoint = $reorderPoint;
        $this->imagePath = $imagePath;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->categoryName = $categoryName;
        $this->barcode = $barcode;
        $this->description = $description;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (string) $row['sku'],
            (string) $row['name'],
            (int) $row['category_id'],
            (string) $row['unit'],
            (string) $row['purchase_price'],
            (string) $row['sale_price'],
            (int) $row['reorder_point'],
            isset($row['image_path']) && $row['image_path'] !== null ? (string) $row['image_path'] : null,
            (bool) $row['is_active'],
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
            isset($row['category_name']) ? (string) $row['category_name'] : null,
            isset($row['barcode']) && $row['barcode'] !== null ? (string) $row['barcode'] : null,
            isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'unit' => $this->unit,
            'purchase_price' => $this->purchasePrice,
            'sale_price' => $this->salePrice,
            'reorder_point' => $this->reorderPoint,
            'image_path' => $this->imagePath,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
