<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Product;
use App\Repository\Interface\ProductRepositoryInterface;

class ProductMySQLRepository implements ProductRepositoryInterface
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
                'products',
                'p',
                'p.*, c.name AS category_name',
                $this->categoryJoin(),
                ['p.id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find product';
            $result->data = $row === null ? null : Product::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findBySku($sku)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'products',
                'p',
                'p.*, c.name AS category_name',
                $this->categoryJoin(),
                ['p.sku' => $sku]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find product';
            $result->data = $row === null ? null : Product::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null)
    {
        $result = new Result();

        try {
            $inFilters = [];
            if ($categoryIds !== null && count($categoryIds) > 0) {
                $inFilters['p.category_id'] = $categoryIds;
            }
            if ($warehouseIds !== null && count($warehouseIds) > 0) {
                $inFilters['ps.warehouse_id'] = $warehouseIds;
            }

            $having = $this->buildStockStatusHaving($stockStatus);

            // Discrete SKU / Product Name filters (own field per column, ANDed)
            // take precedence over the legacy combined $search (OR across both
            // columns) — the combined form is kept only for callers, such as
            // the Reports line-items table, that still use one free-text box.
            // The SKU field itself also matches barcode (OR'd against SKU,
            // not a separate field) — both are scannable product codes a
            // warehouse worker would look up the same way.
            $likeFilters = [];
            $searchColumns = [];
            $searchTerm = null;
            if ($sku !== null && $sku !== '') {
                $searchColumns = ['p.sku', 'p.barcode'];
                $searchTerm = $sku;
            }
            if ($productName !== null && $productName !== '') {
                $likeFilters['p.name'] = $productName;
            }
            if ($searchColumns === [] && $likeFilters === []) {
                $searchColumns = ['p.sku', 'p.name'];
                $searchTerm = $search;
            }

            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.*, ANY_VALUE(c.name) AS category_name',
                $this->productJoins($warehouseIds, $having !== null),
                $searchColumns,
                $searchTerm,
                [],
                'p.name ASC',
                $limit,
                $offset,
                $likeFilters,
                [],
                $this->productGroupBy($warehouseIds, $having),
                $having,
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list products';
            $result->data = array_map(function ($row) { return Product::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
           error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
           $result->code = Result::CODE_INTERNAL;
           $result->info = Result::MESSAGE_FAILED_FUNCTION;
           $result->data = null;
       }

       return $result;
    }

    public function countAll($search = null, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null)
    {
        $result = new Result();

        try {
            $inFilters = [];
            if ($categoryIds !== null && count($categoryIds) > 0) {
                $inFilters['p.category_id'] = $categoryIds;
            }
            if ($warehouseIds !== null && count($warehouseIds) > 0) {
                $inFilters['ps.warehouse_id'] = $warehouseIds;
            }

            $having = $this->buildStockStatusHaving($stockStatus);

            $likeFilters = [];
            $searchColumns = [];
            $searchTerm = null;
            if ($sku !== null && $sku !== '') {
                $searchColumns = ['p.sku', 'p.barcode'];
                $searchTerm = $sku;
            }
            if ($productName !== null && $productName !== '') {
                $likeFilters['p.name'] = $productName;
            }
            if ($searchColumns === [] && $likeFilters === []) {
                $searchColumns = ['p.sku', 'p.name'];
                $searchTerm = $search;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count products';
            $result->data = $this->queryBuilder->countAll(
                'products',
                'p',
                $this->productJoins($warehouseIds, $having !== null),
                $searchColumns,
                $searchTerm,
                [],
                $likeFilters,
                [],
                [],
                $inFilters,
                $this->productGroupBy($warehouseIds, $having),
                $having
            );
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = 0;
        }

        return $result;
    }

    private function buildStockStatusHaving(?string $stockStatus): ?array
    {
        if ($stockStatus === null) {
            return null;
        }

        // Inactive products have their own "Inactive" option; the three stock
        // states only describe active products (otherwise "In Stock" listed rows
        // badged "Inactive").
        return match ($stockStatus) {
            'in_stock' => ['sql' => 'p.is_active = 1 AND COALESCE(SUM(ps.quantity), 0) > 0 AND COALESCE(SUM(ps.quantity), 0) >= p.reorder_point', 'params' => []],
            'low_stock' => ['sql' => 'p.is_active = 1 AND COALESCE(SUM(ps.quantity), 0) > 0 AND COALESCE(SUM(ps.quantity), 0) < p.reorder_point', 'params' => []],
            'out_of_stock' => ['sql' => 'p.is_active = 1 AND COALESCE(SUM(ps.quantity), 0) = 0', 'params' => []],
            'inactive' => ['sql' => 'p.is_active = 0', 'params' => []],
            'active' => ['sql' => 'p.is_active = 1', 'params' => []],
            default => null,
        };
    }

    // One row per product whenever product_stocks is joined: the warehouse
    // filter's INNER JOIN yields one row per matching warehouse, so selecting two
    // warehouses listed every product twice and doubled the total.
    private function productGroupBy($warehouseIds, $having)
    {
        $hasWarehouseJoin = $warehouseIds !== null && count($warehouseIds) > 0;
        if ($having !== null || $hasWarehouseJoin) {
            return 'p.id';
        }

        return null;
    }

    public function findAllActive()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.*, c.name AS category_name',
                $this->categoryJoin(),
                [],
                null,
                ['p.is_active' => 1],
                'p.name ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list active products';
            $result->data = array_map(function ($row) { return Product::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function skuExists($sku, $excludeId = null)
    {
        $result = new Result();

        try {
            $filters = ['sku' => $sku];
            $notEqualsFilters = [];

            if ($excludeId !== null) {
                $notEqualsFilters['id'] = $excludeId;
            }

            $count = $this->queryBuilder->countAll('products', null, [], [], null, $filters, [], $notEqualsFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check SKU';
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
            $result->data = $this->queryBuilder->insert('products', [
                'sku' => (string) $data['sku'],
                'barcode' => !empty($data['barcode']) ? (string) $data['barcode'] : null,
                'name' => (string) $data['name'],
                'description' => !empty($data['description']) ? (string) $data['description'] : null,
                'category_id' => (int) $data['category_id'],
                'unit' => (string) $data['unit'],
                'purchase_price' => (string) $data['purchase_price'],
                'sale_price' => (string) $data['sale_price'],
                'reorder_point' => (int) $data['reorder_point'],
                'image_path' => $data['image_path'] ?? null,
                'is_active' => isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create product';
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
                'products',
                [
                    'sku' => (string) $data['sku'],
                    'barcode' => !empty($data['barcode']) ? (string) $data['barcode'] : null,
                    'name' => (string) $data['name'],
                    'description' => !empty($data['description']) ? (string) $data['description'] : null,
                    'category_id' => (int) $data['category_id'],
                    'unit' => (string) $data['unit'],
                    'purchase_price' => (string) $data['purchase_price'],
                    'sale_price' => (string) $data['sale_price'],
                    'reorder_point' => (int) $data['reorder_point'],
                    // The edit form's Active/Inactive toggle — previously dropped, so a
                    // product could never be deactivated from the edit page.
                    'is_active' => !empty($data['is_active']) ? 1 : 0,
                ],
                ['id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update product';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateImagePath($id, $imagePath)
    {
        $result = new Result();

        try {
            $this->queryBuilder->update('products', ['image_path' => $imagePath], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update product image';
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
            $this->queryBuilder->update('products', ['is_active' => $active ? 1 : 0], ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update product status';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    /**
     * Returns aggregate product counts by stock status for the KPI metrics bar.
     * A product's effective stock is the SUM across all active warehouses.
     */
    public function countByStockStatus()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.id, p.reorder_point, p.is_active, COALESCE(SUM(ps.quantity), 0) AS total_stock',
                [
                    ['type' => 'LEFT', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'],
                    ['type' => 'LEFT', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = ps.warehouse_id AND w.is_active = 1'],
                ],
                [],
                null,
                [],
                null,
                0,
                0,
                [],
                [],
                'p.id'
            );

            $counts = ['total' => 0, 'normal' => 0, 'low' => 0, 'out' => 0, 'inactive' => 0];

            foreach ($rows as $row) {
                if ((int) ($row['is_active']) === 0) {
                    $counts['inactive']++;
                    continue;
                }
                $counts['total']++;
                $stock = (int) ($row['total_stock'] ?? 0);
                $reorderPt = (int) ($row['reorder_point'] ?? 0);
                if ($stock === 0) {
                    $counts['out']++;
                } elseif ($stock < $reorderPt) {
                    $counts['low']++;
                } else {
                    $counts['normal']++;
                }
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count products by stock status';
            $result->data = $counts;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());

            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = ['total' => 0, 'normal' => 0, 'low' => 0, 'out' => 0, 'inactive' => 0];
        }

        return $result;
    }

    public function findLowStock()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.*, c.name AS category_name, COALESCE(SUM(ps.quantity), 0) AS total_stock',
                [
                    ['type' => 'LEFT', 'table' => 'categories', 'alias' => 'c', 'on' => 'c.id = p.category_id'],
                    ['type' => 'LEFT', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'],
                ],
                [],
                null,
                [],
                'total_stock ASC',
                0,
                0,
                [],
                [],
                'p.id',
                ['sql' => 'COALESCE(SUM(ps.quantity), 0) < p.reorder_point', 'params' => []]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list low stock products';
            $result->data = array_map(function ($row) { return Product::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Shared by findById()/findBySku()/findAll()/countAll()/findAllActive()
    // via QueryBuilder — findLowStock() above has its own GROUP BY/HAVING
    // aggregate and is deliberately kept separate rather than forced to fit.
    private function categoryJoin()
    {
        return [
            ['table' => 'categories', 'alias' => 'c', 'on' => 'c.id = p.category_id'],
        ];
    }

    // Adds an INNER JOIN to product_stocks (filtered to selected warehouses via
    // the 'ps.warehouse_id' IN-filter passed alongside) only when a
    // warehouse filter is actually requested — restricts the product list to
    // products that have a stock record at those warehouses. Left as the plain
    // categoryJoin() otherwise, so the default (no warehouse filter) query
    // shape and row count are unchanged.
    private function productJoins($warehouseIds, $needsStockJoin = false)
    {
        $joins = $this->categoryJoin();

        if ($warehouseIds !== null && count($warehouseIds) > 0) {
            $joins[] = ['type' => 'INNER', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'];
        } elseif ($needsStockJoin) {
            // The stockStatus HAVING clause below aggregates ps.quantity, so the
            // join must exist even when no warehouse filter narrows it — otherwise
            // "ps" is an unknown alias and the query fails (silently, inside the
            // repository's catch block) whenever a stock-status filter is applied
            // without also filtering by warehouse.
            $joins[] = ['type' => 'LEFT', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'];
        }

        return $joins;
    }
}
