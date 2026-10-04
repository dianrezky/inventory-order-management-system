<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Repository\Interface\PermissionRepositoryInterface;

class PermissionFakeRepository implements PermissionRepositoryInterface
{
    private $grouped;

    // $grouped: ['permission_key' => ['Admin', 'WarehouseStaff'], ...]
    public function __construct($grouped = [])
    {
        $this->grouped = $grouped;
    }

    public function findAllGrouped()
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find role permissions';
        $result->data = $this->grouped;

        return $result;
    }
}
