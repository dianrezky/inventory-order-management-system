<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Warehouse;
use App\Repository\Interface\WarehouseRepositoryInterface;

class WarehouseMySQLRepository implements WarehouseRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findById($id)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'warehouses',
                null,
                'id, code, name, location, is_active, created_at, updated_at',
                [],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find warehouse';
            $result->data = $row === null ? null : Warehouse::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $code = null, $name = null, $location = null)
    {
        $result = new Result();

        $inFilters = [];
        if ($statuses !== null && count($statuses) > 0) {
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = 1; }
                elseif ($s === 'inactive') { $isActiveValues[] = 0; }
            }
            if (count($isActiveValues) > 0) {
                $inFilters['is_active'] = $isActiveValues;
            }
        }

        // Discrete Code / Name / Location fields (own field per column,
        // ANDed) take precedence over the legacy combined $search (OR across
        // all three columns) — same precedence rule as
        // ProductMySQLRepository::findAll()'s sku/productName split.
        $likeFilters = [];
        if ($code !== null && $code !== '') { $likeFilters['code'] = $code; }
        if ($name !== null && $name !== '') { $likeFilters['name'] = $name; }
        if ($location !== null && $location !== '') { $likeFilters['location'] = $location; }
        $searchColumns = $likeFilters === [] ? ['code', 'name', 'location'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $rows = $this->queryBuilder->findAll(
                'warehouses',
                null,
                'id, code, name, location, is_active, created_at, updated_at',
                [],
                $searchColumns,
                $searchTerm,
                [],
                'name ASC',
                $limit,
                $offset,
                $likeFilters,
                [],
                null,
                null,
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list warehouses';
            $result->data = array_map(function ($row) { return Warehouse::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAllActive()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'warehouses',
                null,
                'id, code, name, location, is_active, created_at, updated_at',
                [],
                [],
                null,
                ['is_active' => 1],
                'name ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list active warehouses';
            $result->data = array_map(function ($row) { return Warehouse::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function codeExists($code, $excludeId = null)
    {
        $result = new Result();

        try {
            $filters = ['code' => $code];
            $notEqualsFilters = [];

            if ($excludeId !== null) {
                $notEqualsFilters['id'] = $excludeId;
            }

            $count = $this->queryBuilder->countAll('warehouses', null, [], [], null, $filters, [], $notEqualsFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check warehouse code';
            $result->data = $count > 0;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function create($data)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('warehouses', [
                'code' => (string) $data['code'],
                'name' => (string) $data['name'],
                'location' => $data['location'] ?? null,
                'is_active' => 1,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create warehouse';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function update($id, $data)
    {
        $result = new Result();

        try {
            $this->queryBuilder->update(
                'warehouses',
                [
                    'code' => (string) $data['code'],
                    'name' => (string) $data['name'],
                    'location' => $data['location'] ?? null,
                ],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update warehouse';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function setActive($id, $active)
    {
        $result = new Result();

        try {
            $this->queryBuilder->update('warehouses', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update warehouse status';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function delete($id)
    {
        return $this->setActive($id, false);
    }
}
