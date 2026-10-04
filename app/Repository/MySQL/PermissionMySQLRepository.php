<?php

namespace App\Repository\MySQL;

use App\Core\Result;
use App\Repository\Interface\PermissionRepositoryInterface;

class PermissionMySQLRepository implements PermissionRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findAllGrouped()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll('role_permissions', null, 'permission_key, role');

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['permission_key']][] = $row['role'];
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find role permissions';
            $result->data = $grouped;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
