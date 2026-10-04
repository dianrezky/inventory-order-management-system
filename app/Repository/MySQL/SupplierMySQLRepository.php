<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Supplier;
use App\Repository\Interface\SupplierRepositoryInterface;

class SupplierMySQLRepository implements SupplierRepositoryInterface
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
                'suppliers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
                [],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find supplier';
            $result->data = $row === null ? null : Supplier::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $contactPerson = null, $email = null)
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

        // Discrete Name / Contact Person / Email fields (own field per
        // column, ANDed) take precedence over the legacy combined $search
        // (OR across all three columns) — same precedence rule as
        // ProductMySQLRepository::findAll()'s sku/productName split.
        $likeFilters = [];
        if ($name !== null && $name !== '') { $likeFilters['name'] = $name; }
        if ($contactPerson !== null && $contactPerson !== '') { $likeFilters['contact_person'] = $contactPerson; }
        if ($email !== null && $email !== '') { $likeFilters['email'] = $email; }
        $searchColumns = $likeFilters === [] ? ['name', 'contact_person', 'email'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $rows = $this->queryBuilder->findAll(
                'suppliers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
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
            $result->info = 'Success to list suppliers';
            $result->data = array_map(function ($row) { return Supplier::fromArray($row); }, $rows);
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
                'suppliers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
                [],
                [],
                null,
                ['is_active' => 1],
                'name ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list active suppliers';
            $result->data = array_map(function ($row) { return Supplier::fromArray($row); }, $rows);
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
            $result->data = $this->queryBuilder->insert('suppliers', [
                'name' => (string) $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => 1,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create supplier';
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
                'suppliers',
                [
                    'name' => (string) $data['name'],
                    'contact_person' => $data['contact_person'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                ],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update supplier';
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
            $this->queryBuilder->update('suppliers', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update supplier status';
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
