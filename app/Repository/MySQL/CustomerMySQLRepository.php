<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Customer;
use App\Repository\Interface\CustomerRepositoryInterface;

class CustomerMySQLRepository implements CustomerRepositoryInterface
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
                'customers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
                [],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find customer';
            $result->data = $row === null ? null : Customer::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $email = null, $phone = null, $contactPerson = null)
    {
        $result = new Result();

        $inFilters = [];
        if ($statuses !== null && count($statuses) > 0) {
            // Map 'active'/'inactive' strings to is_active integer values
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = 1; }
                elseif ($s === 'inactive') { $isActiveValues[] = 0; }
            }
            if (count($isActiveValues) > 0) {
                $inFilters['is_active'] = $isActiveValues;
            }
        }

        // Discrete Name / Email / Phone / Contact Person fields (own field
        // per column, ANDed) take precedence over the legacy combined
        // $search (OR across all four columns) — same precedence rule as
        // ProductMySQLRepository::findAll()'s sku/productName split.
        $likeFilters = [];
        if ($name !== null && $name !== '') { $likeFilters['name'] = $name; }
        if ($email !== null && $email !== '') { $likeFilters['email'] = $email; }
        if ($phone !== null && $phone !== '') { $likeFilters['phone'] = $phone; }
        if ($contactPerson !== null && $contactPerson !== '') { $likeFilters['contact_person'] = $contactPerson; }
        $searchColumns = $likeFilters === [] ? ['name', 'contact_person', 'email', 'phone'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $rows = $this->queryBuilder->findAll(
                'customers',
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
            $result->info = 'Success to list customers';
            $result->data = array_map(function ($row) { return Customer::fromArray($row); }, $rows);
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
                'customers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
                [],
                [],
                null,
                ['is_active' => 1],
                'name ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list active customers';
            $result->data = array_map(function ($row) { return Customer::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Distinct from findAll(): adds sort column/direction and an is_active
    // filter, used by the customer list screen's own search form.
    public function search($search, $sortBy, $sortDir, $filter, $page, $perPage)
    {
        $result = new Result();

        try {
            $orderBy = $this->safeColumn($sortBy) . ' ' . ($sortDir === 'desc' ? 'DESC' : 'ASC');
            $offset = $perPage > 0 ? ($page - 1) * $perPage : 0;

            $rows = $this->queryBuilder->findAll(
                'customers',
                null,
                'id, name, contact_person, phone, email, address, is_active, created_at, updated_at',
                [],
                ['name', 'email', 'phone', 'contact_person'],
                $search,
                $filter !== null ? ['is_active' => (int) $filter] : [],
                $orderBy,
                $perPage,
                $offset
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to search customers';
            $result->data = array_map(function ($row) { return Customer::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countSearch($search, $filter)
    {
        $result = new Result();

        try {
            $total = $this->queryBuilder->countAll(
                'customers',
                null,
                [],
                ['name', 'email', 'phone', 'contact_person'],
                $search,
                $filter !== null ? ['is_active' => (int) $filter] : []
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count customers';
            $result->data = $total;
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
            $result->data = $this->queryBuilder->insert('customers', [
                'name' => (string) $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => 1,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create customer';
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
                'customers',
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
            $result->info = 'Success to update customer';
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
            $this->queryBuilder->update('customers', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update customer status';
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

    private function safeColumn($col)
    {
        $allowed = ['id', 'name', 'email', 'phone', 'contact_person', 'is_active', 'created_at', 'updated_at'];

        return in_array($col, $allowed, true) ? $col : 'name';
    }
}
