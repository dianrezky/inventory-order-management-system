<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\ProductStock;
use App\Repository\Interface\ProductStockRepositoryInterface;

class ProductStockMySQLRepository implements ProductStockRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function find($productId, $warehouseId)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'product_stocks',
                null,
                '*',
                [],
                ['product_id' => $productId, 'warehouse_id' => $warehouseId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find product stock';
            $result->data = $row === null ? null : ProductStock::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // ADR-002: must only be called inside an existing Database transaction. A
    // PDO error here is fatal to the caller's transaction either way, so it is
    // still caught and shaped into a Result rather than left to throw raw.
    public function lockForUpdate($productId, $warehouseId)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'product_stocks',
                null,
                '*',
                [],
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                [],
                true
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to lock product stock';
            $result->data = $row === null ? null : ProductStock::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function create($productId, $warehouseId, $quantity = 0)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('product_stocks', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create product stock';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function incrementQuantity($productId, $warehouseId, $delta)
    {
        $result = new Result();

        try {
            $this->queryBuilder->incrementColumn(
                'product_stocks',
                'quantity',
                $delta,
                ['product_id' => $productId, 'warehouse_id' => $warehouseId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update product stock quantity';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function totalInventoryValue()
    {
        $result = new Result();

        try {
            $value = $this->queryBuilder->scalar(
                'product_stocks',
                'ps',
                'COALESCE(SUM(ps.quantity * p.purchase_price), 0)',
                [['type' => 'INNER', 'table' => 'products', 'alias' => 'p', 'on' => 'p.id = ps.product_id']],
                ['p.is_active' => 1]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute total inventory value';
            $result->data = (string) $value;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function sumByProductId($productId)
    {
        $result = new Result();

        try {
            $value = $this->queryBuilder->scalar(
                'product_stocks',
                null,
                'COALESCE(SUM(quantity), 0)',
                [],
                ['product_id' => $productId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to sum product stock';
            $result->data = (int) $value;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAllWithProduct($productId)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'product_stocks',
                'ps',
                'ps.quantity, w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name, w.is_active AS warehouse_active',
                [['type' => 'INNER', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = ps.warehouse_id']],
                [],
                null,
                ['product_id' => $productId],
                'w.code ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list product stock by warehouse';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findByWarehouse($warehouseId, $limit = 10, $offset = 0)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'product_stocks',
                'ps',
                'ps.product_id, ps.quantity, ps.updated_at, '
                    . 'p.sku, p.name, p.unit, p.reorder_point, p.is_active, '
                    . 'c.name AS category_name',
                [
                    ['type' => 'INNER', 'table' => 'products',   'alias' => 'p', 'on' => 'p.id = ps.product_id'],
                    ['type' => 'LEFT',  'table' => 'categories', 'alias' => 'c', 'on' => 'c.id = p.category_id'],
                ],
                [],
                null,
                ['ps.warehouse_id' => (int) $warehouseId],
                'p.name ASC',
                (int) $limit,
                (int) $offset
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list stocks by warehouse';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countByWarehouse($warehouseId)
    {
        $result = new Result();

        try {
            $count = $this->queryBuilder->countAll(
                'product_stocks',
                'ps',
                [],
                [],
                null,
                ['ps.warehouse_id' => (int) $warehouseId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count stocks by warehouse';
            $result->data = (int) $count;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function sumQuantityByWarehouse($warehouseId)
    {
        $result = new Result();

        try {
            $value = $this->queryBuilder->scalar(
                'product_stocks',
                null,
                'COALESCE(SUM(quantity), 0)',
                [],
                ['warehouse_id' => (int) $warehouseId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to sum stock quantity by warehouse';
            $result->data = (int) $value;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    /**
     * Returns products whose TOTAL stock across all warehouses is below the
     * product's reorder point (SKU, name, reorder point, and the aggregated
     * quantity). Product-level per the Project Brief DASH-01/JOB-01 ("produk di
     * bawah reorder point") — the same aggregate basis as ProductRepository::findLowStock().
     */
    public function findCriticalStock($limit = 10)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.id AS product_id, p.sku, p.name AS product_name,'
                    . ' p.reorder_point, COALESCE(SUM(ps.quantity), 0) AS quantity',
                [
                    ['type' => 'LEFT', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'],
                ],
                [],
                null,
                // A deactivated product is not an actionable alert
                ['p.is_active' => 1],
                'quantity ASC',
                (int) $limit,
                0,
                [],
                [],
                'p.id',
                ['params' => [], 'sql' => 'COALESCE(SUM(ps.quantity), 0) < p.reorder_point']
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list critical stock';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countCriticalStock()
    {
        $result = new Result();

        try {
            // Same product-level scope as findCriticalStock() but uncapped: count
            // the DISTINCT products whose total stock is below reorder point. The
            // grouped id rows are counted in PHP to avoid a grouped-COUNT subquery.
            $rows = $this->queryBuilder->findAll(
                'products',
                'p',
                'p.id',
                [
                    ['type' => 'LEFT', 'table' => 'product_stocks', 'alias' => 'ps', 'on' => 'ps.product_id = p.id'],
                ],
                [],
                null,
                ['p.is_active' => 1],
                null,
                0,
                0,
                [],
                [],
                'p.id',
                ['params' => [], 'sql' => 'COALESCE(SUM(ps.quantity), 0) < p.reorder_point']
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count critical stock';
            $result->data = is_array($rows) ? count($rows) : 0;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function getCategoryValuation()
    {
        $result = new Result();

        try {
            $columns = implode(', ', [
                'c.id AS category_id',
                'c.name AS category_name',
                'COUNT(DISTINCT p.id) AS sku_count',
                'COALESCE(SUM(ps.quantity), 0) AS total_quantity',
                'COALESCE(SUM(ps.quantity * p.purchase_price), 0) AS total_valuation',
            ]);

            $rows = $this->queryBuilder->findAll(
                'product_stocks',
                'ps',
                $columns,
                [
                    ['type' => 'INNER', 'table' => 'products', 'alias' => 'p', 'on' => 'p.id = ps.product_id'],
                    ['type' => 'LEFT',  'table' => 'categories', 'alias' => 'c', 'on' => 'c.id = p.category_id'],
                ],
                [],                    // searchColumns
                null,                  // search
                ['p.is_active' => 1],  // filters
                null,                  // orderBy
                0,                     // limit
                0,                     // offset
                [],                    // likeFilters
                [],                    // notEqualsFilters
                'c.id',                // groupBy
                null,                  // having
                [],                    // operatorFilters
                []                     // inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to get category valuation';
            $result->data = is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
