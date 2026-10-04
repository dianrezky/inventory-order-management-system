<?php

namespace App\Entity;

use DateTimeImmutable;

// Entity layer: public properties only, no behavior (AGENT.md §9).
class Customer
{
    public $id;
    public $name;
    public $contactPerson;
    public $phone;
    public $email;
    public $address;
    public $isActive;
    public $createdAt;
    public $updatedAt;

    public function __construct(
        $id,
        $name,
        $contactPerson,
        $phone,
        $email,
        $address,
        $isActive,
        $createdAt = null,
        $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->contactPerson = $contactPerson;
        $this->phone = $phone;
        $this->email = $email;
        $this->address = $address;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            isset($row['contact_person']) && $row['contact_person'] !== null ? (string) $row['contact_person'] : null,
            isset($row['phone']) && $row['phone'] !== null ? (string) $row['phone'] : null,
            isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
            isset($row['address']) && $row['address'] !== null ? (string) $row['address'] : null,
            (bool) $row['is_active'],
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contactPerson,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
