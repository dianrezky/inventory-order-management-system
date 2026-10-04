<?php

namespace App\Entity;

use App\Entity\Role;
use DateTimeImmutable;

// $role holds a Role backed enum, matching the users.role ENUM column.
class User
{
    public $id;
    public $name;
    public $email;
    public $passwordHash;
    public $role;
    public $isActive;
    public $createdAt;
    public $updatedAt;

    public function __construct(
        $id,
        $name,
        $email,
        $passwordHash,
        $role,
        $isActive,
        $createdAt = null,
        $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray($row)
    {
        $roleStr = (string) $row['role'];

        // Convert DB string to Role backed enum (TDB-001/003)
        $roleEnum = Role::tryFrom($roleStr);
        if ($roleEnum === null) {
            // Fail closed on an unrecognized DB value: least-privileged role,
            // never Admin — this must never be a silent privilege escalation.
            $roleEnum = Role::WarehouseStaff;
        }

        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            $roleEnum,
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
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            // Backed enum -> string for DB storage
            'role' => $this->role instanceof Role ? $this->role->value : (string) $this->role,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
