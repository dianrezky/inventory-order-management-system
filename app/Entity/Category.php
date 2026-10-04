<?php

namespace App\Entity;

use DateTimeImmutable;

// Entity layer: public properties only, no behavior (AGENT.md §9).
class Category
{
    public $id;
    public $code;
    public $name;
    public $description;
    public $isActive;
    // Transient — only populated by list queries that join against products
    // (CategoryRepositoryInterface::findAllPaged()); 0 everywhere else
    // (findById, findAllActive, ...), never persisted on this entity itself.
    public $assignedSkuCount;
    public $createdAt;
    public $updatedAt;

    public function __construct(
        $id,
        $code,
        $name,
        $description,
        $isActive,
        $assignedSkuCount = 0,
        $createdAt = null,
        $updatedAt = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->isActive = $isActive;
        $this->assignedSkuCount = $assignedSkuCount;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            isset($row['code']) ? (string) $row['code'] : '',
            (string) $row['name'],
            isset($row['description']) ? (string) $row['description'] : null,
            (bool) $row['is_active'],
            isset($row['assigned_sku_count']) ? (int) $row['assigned_sku_count'] : 0,
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'assigned_sku_count' => $this->assignedSkuCount,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
