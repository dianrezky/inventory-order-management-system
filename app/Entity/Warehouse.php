<?php

namespace App\Entity;

use DateTimeImmutable;

class Warehouse
{
    public $id;
    public $code;
    public $name;
    public $location;
    public $isActive;
    public $createdAt;
    public $updatedAt;

    public function __construct(
        $id,
        $code,
        $name,
        $location,
        $isActive,
        $createdAt = null,
        $updatedAt = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->location = $location;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            isset($row['location']) && $row['location'] !== null ? (string) $row['location'] : null,
            (bool) $row['is_active'],
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
            'location' => $this->location,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
