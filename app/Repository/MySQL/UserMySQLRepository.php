<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\User;
use App\Repository\Interface\UserRepositoryInterface;

// All queries use PDO prepared statements exclusively (AGENT.md §12.4).
class UserMySQLRepository implements UserRepositoryInterface
{
    private $queryBuilder;

    private const COLUMNS = 'id, name, email, password_hash, role, is_active, created_at, updated_at';

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findById($id)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne('users', null, self::COLUMNS, [], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find user';
            $result->data = $row === null ? null : User::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findByEmail($email)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne('users', null, self::COLUMNS, [], ['email' => $email]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find user';
            $result->data = $row === null ? null : User::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $isActive = null, $role = null, $name = null, $email = null)
    {
        $result = new Result();

        $inFilters = [];
        if ($isActive !== null && count($isActive) > 0) {
            $isActiveValues = [];
            foreach ($isActive as $s) {
                if ($s === 'active') { $isActiveValues[] = 1; }
                elseif ($s === 'inactive') { $isActiveValues[] = 0; }
            }
            if (count($isActiveValues) > 0) {
                $inFilters['is_active'] = $isActiveValues;
            }
        }
        if ($role !== null && count($role) > 0) {
            $inFilters['role'] = $role;
        }

        // Discrete Name / Email fields (own field per column, ANDed) take
        // precedence over the legacy combined $search (OR across both
        // columns) — same precedence rule as
        // ProductMySQLRepository::findAll()'s sku/productName split.
        $likeFilters = [];
        if ($name !== null && $name !== '') { $likeFilters['name'] = $name; }
        if ($email !== null && $email !== '') { $likeFilters['email'] = $email; }
        $searchColumns = $likeFilters === [] ? ['name', 'email'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $rows = $this->queryBuilder->findAll(
                'users',
                null,
                self::COLUMNS,
                [],
                $searchColumns,
                $searchTerm,
                [],
                'name ASC',
                0,
                0,
                $likeFilters,
                [],
                null,
                null,
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list users';
            $result->data = array_map(function ($row) { return User::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function emailExists($email, $excludeId = null)
    {
        $result = new Result();

        try {
            $filters = ['email' => $email];
            $notEqualsFilters = [];

            if ($excludeId !== null) {
                $notEqualsFilters['id'] = $excludeId;
            }

            $count = $this->queryBuilder->countAll('users', null, [], [], null, $filters, [], $notEqualsFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check email existence';
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
            $result->data = $this->queryBuilder->insert('users', [
                'name' => (string) $data['name'],
                'email' => (string) $data['email'],
                'password_hash' => (string) $data['password_hash'],
                'role' => (string) $data['role'],
                'is_active' => 1,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create user';
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
                'users',
                [
                    'name' => (string) $data['name'],
                    'email' => (string) $data['email'],
                    'role' => (string) $data['role'],
                ],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update user';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updatePassword($id, $passwordHash)
    {
        $result = new Result();

        try {
            $this->queryBuilder->update('users', ['password_hash' => $passwordHash], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update user password';
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
            $this->queryBuilder->update('users', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update user status';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
