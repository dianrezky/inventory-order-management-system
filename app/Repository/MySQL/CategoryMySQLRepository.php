<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Category;
use App\Repository\Interface\CategoryRepositoryInterface;
use App\Service\CategoryService;

class CategoryMySQLRepository implements CategoryRepositoryInterface
{
    private $queryBuilder;

    // Whitelisted ORDER BY fragments — $sort is a caller-controlled string, so
    // it is only ever used to look up a fragment here, never interpolated
    // directly into SQL (CLAUDE.md §4: no query concatenation with user input).
    private const SORT_MAP = [
        CategoryService::SORT_NAME_ASC => 'c.name ASC',
        CategoryService::SORT_NAME_DESC => 'c.name DESC',
        CategoryService::SORT_CODE_ASC => 'c.code ASC',
        CategoryService::SORT_NEWEST => 'c.created_at DESC',
        CategoryService::SORT_SKUS_DESC => 'assigned_sku_count DESC, c.name ASC',
    ];

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findById($id)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'categories',
                null,
                'id, code, name, description, is_active, created_at, updated_at',
                [],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find category';
            $result->data = $row === null ? null : Category::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null)
    {
        $result = new Result();

        $inFilters = $this->statusInFilters($statuses);

        try {
            $rows = $this->queryBuilder->findAll(
                'categories',
                null,
                'id, code, name, description, is_active, created_at, updated_at',
                [],
                ['name', 'description', 'code'],
                $search,
                [],
                'name ASC',
                $limit,
                $offset,
                [],
                [],
                null,
                null,
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list categories';
            $result->data = array_map(function ($row) { return Category::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAllPaged($search, $statuses, $sort, $limit, $offset, $categoryName = null, $categoryCode = null)
    {
        $result = new Result();

        $inFilters = $this->statusInFilters($statuses);
        $orderBy = self::SORT_MAP[$sort] ?? self::SORT_MAP[CategoryService::SORT_NAME_ASC];

        // Discrete Category Name / Category Code fields (own field per
        // column, ANDed) take precedence over the legacy combined $search
        // (OR across name/description/code) — same precedence rule as
        // ProductMySQLRepository::findAll()'s sku/productName split.
        // Description is deliberately not split into its own field: it is
        // free-form long text, not an identifying attribute like the other
        // split fields across these list screens.
        $likeFilters = [];
        if ($categoryName !== null && $categoryName !== '') { $likeFilters['c.name'] = $categoryName; }
        if ($categoryCode !== null && $categoryCode !== '') { $likeFilters['c.code'] = $categoryCode; }
        $searchColumns = $likeFilters === [] ? ['c.name', 'c.description', 'c.code'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $rows = $this->queryBuilder->findAll(
                'categories',
                'c',
                'c.id, c.code, c.name, c.description, c.is_active, c.created_at, c.updated_at, COUNT(p.id) AS assigned_sku_count',
                [
                    ['type' => 'LEFT', 'table' => 'products', 'alias' => 'p', 'on' => 'p.category_id = c.id'],
                ],
                $searchColumns,
                $searchTerm,
                [],
                $orderBy,
                $limit,
                $offset,
                $likeFilters,
                [],
                'c.id',
                null,
                [],
                $this->prefixed($inFilters, 'c.')
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list categories';
            $result->data = array_map(function ($row) { return Category::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countFiltered($search, $statuses, $categoryName = null, $categoryCode = null)
    {
        $result = new Result();

        $inFilters = $this->statusInFilters($statuses);

        $likeFilters = [];
        if ($categoryName !== null && $categoryName !== '') { $likeFilters['name'] = $categoryName; }
        if ($categoryCode !== null && $categoryCode !== '') { $likeFilters['code'] = $categoryCode; }
        $searchColumns = $likeFilters === [] ? ['name', 'description', 'code'] : [];
        $searchTerm = $likeFilters === [] ? $search : null;

        try {
            $count = $this->queryBuilder->countAll(
                'categories',
                null,
                [],
                $searchColumns,
                $searchTerm,
                [],
                $likeFilters,
                [],
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count categories';
            $result->data = $count;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = 0;
        }

        return $result;
    }

    public function getMetrics()
    {
        $result = new Result();

        try {
            // countAll() only wraps GROUP BY safely when there is no HAVING
            // (see its own comment) — "empty categories" needs both together,
            // so pull every category's live SKU count once (this table is
            // small master data, never product-scale) and reduce in PHP
            // rather than fight the query builder's HAVING-after-GROUP-BY gap.
            $rows = $this->queryBuilder->findAll(
                'categories',
                'c',
                'c.id, c.is_active, COUNT(p.id) AS assigned_sku_count',
                [
                    ['type' => 'LEFT', 'table' => 'products', 'alias' => 'p', 'on' => 'p.category_id = c.id'],
                ],
                [],
                null,
                [],
                null,
                0,
                0,
                [],
                [],
                'c.id'
            );

            $total = count($rows);
            $active = 0;
            $totalAssignedSkus = 0;
            $empty = 0;

            foreach ($rows as $row) {
                if ((int) $row['is_active'] === 1) {
                    $active++;
                }
                $skuCount = (int) $row['assigned_sku_count'];
                $totalAssignedSkus += $skuCount;
                if ($skuCount === 0) {
                    $empty++;
                }
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute category metrics';
            $result->data = [
                'total' => $total,
                'active' => $active,
                'totalAssignedSkus' => $totalAssignedSkus,
                'empty' => $empty,
            ];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = ['total' => 0, 'active' => 0, 'totalAssignedSkus' => 0, 'empty' => 0];
        }

        return $result;
    }

    public function countAssignedSkus($id)
    {
        $result = new Result();

        try {
            $count = $this->queryBuilder->countAll('products', null, [], [], null, ['category_id' => $id], [], [], [], []);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count assigned SKUs';
            $result->data = (int) $count;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = 0;
        }

        return $result;
    }

    public function findAllActive()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'categories',
                null,
                'id, code, name, description, is_active, created_at, updated_at',
                [],
                [],
                null,
                ['is_active' => 1],
                'name ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list active categories';
            $result->data = array_map(function ($row) { return Category::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function nameExists($name, $excludeId = null)
    {
        $result = new Result();

        try {
            $filters = ['name' => $name];
            $notEqualsFilters = [];

            if ($excludeId !== null) {
                $notEqualsFilters['id'] = $excludeId;
            }

            $count = $this->queryBuilder->countAll('categories', null, [], [], null, $filters, [], $notEqualsFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check category name';
            $result->data = $count > 0;
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

            $count = $this->queryBuilder->countAll('categories', null, [], [], null, $filters, [], $notEqualsFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check category code';
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
            $result->data = $this->queryBuilder->insert('categories', [
                'code' => (string) $data['code'],
                'name' => (string) $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => !empty($data['is_active']) ? 1 : ($data['is_active'] ?? 1),
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create category';
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
                'categories',
                [
                    'code' => (string) $data['code'],
                    'name' => (string) $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'] ? 1 : 0,
                ],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update category';
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
            $this->queryBuilder->update('categories', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update category status';
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
        $result = new Result();

        try {
            // Real hard delete. The service is responsible for checking
            // countAssignedSkus($id) === 0 first; the FK
            // (fk_products_category ... ON DELETE RESTRICT) is the DB-level
            // backstop if that check is ever bypassed.
            $this->queryBuilder->delete('categories', ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to delete category';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    private function statusInFilters($statuses)
    {
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

        return $inFilters;
    }

    private function prefixed($inFilters, $prefix)
    {
        $out = [];
        foreach ($inFilters as $column => $values) {
            $out[$prefix . $column] = $values;
        }

        return $out;
    }
}
