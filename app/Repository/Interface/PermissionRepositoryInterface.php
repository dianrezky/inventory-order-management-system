<?php

namespace App\Repository\Interface;

// role_permissions is reference/lookup data, not a user-editable entity — no
// findAll/update/setActive/delete would make sense here, so this interface
// only declares the one method it actually needs.
interface PermissionRepositoryInterface
{
    // Returns every row grouped as ['permission_key' => ['Admin', 'WarehouseStaff'], ...]
    public function findAllGrouped();
}
