<?php

namespace App\Entity;

// Backed enum mirroring the users.role ENUM column in the database.
enum Role: string
{
    case Admin = 'Admin';
    case Sales = 'Sales';
    case WarehouseStaff = 'WarehouseStaff';

    // Display name for the UI. The label belongs to the role itself, so views
    // never have to look it up in a message table.
    public function label()
    {
        if ($this === self::Admin) {
            return 'Admin';
        }

        if ($this === self::Sales) {
            return 'Sales';
        }

        return 'Warehouse Staff';
    }

    // Same label starting from the stored string value, for callers that only
    // hold the raw column value.
    public static function labelFor($value)
    {
        $role = self::tryFrom((string) $value);

        if ($role === null) {
            return (string) $value;
        }

        return $role->label();
    }
}
