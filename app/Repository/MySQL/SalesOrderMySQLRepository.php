<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\SalesOrder;
use App\Repository\Interface\SalesOrderRepositoryInterface;

class SalesOrderMySQLRepository implements SalesOrderRepositoryInterface
{
    private const JOIN_ITEMS = 'soi.sales_order_id = so.id';
    private const JOIN_CUSTOMER = 'c.id = so.customer_id';
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
                'sales_orders',
                'so',
                'so.*, c.name AS customer_name, w.name AS source_warehouse_name, '
                    . 'uc.name AS created_by_name, ua.name AS approved_by_name, ui.name AS issued_by_name',
                $this->joins(),
                ['so.id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find sales order';
            $result->data = $row === null ? null : SalesOrder::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($userId = null, $filters = [], $limit = 0, $offset = 0)
    {
        $result = new Result();

        try {
            [$searchColumns, $search, $likeFilters] = $this->orderSearchFilters($filters);
            $direction = strtoupper((string) ($filters['sort'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';

            $inFilters = [];
            $equalityFilters = $this->ownerAndStatusFilters($userId, $filters, $inFilters);

            // Aggregates (items_count + total_value) are computed via LEFT JOIN to
            // sales_order_items + GROUP BY so.id so the list view can render the
            // "Items / Qty" and "Total Value" columns (Stitch §12.6 sales-order list)
            // without N+1 queries.
            $joins = array_merge($this->joins(), [
                ['type' => 'LEFT', 'table' => 'sales_order_items', 'alias' => 'soi', 'on' => self::JOIN_ITEMS],
            ]);

            $operatorFilters = [];
            if (!empty($filters['date_from'])) {
                $operatorFilters[] = ['so.order_date', '>=', (string) $filters['date_from']];
            }
            if (!empty($filters['date_to'])) {
                $operatorFilters[] = ['so.order_date', '<=', (string) $filters['date_to']];
            }

            $rows = $this->queryBuilder->findAll(
                'sales_orders',
                'so',
                'so.*, c.name AS customer_name, w.name AS source_warehouse_name, '
                    . 'uc.name AS created_by_name, ua.name AS approved_by_name, '
                    . 'COUNT(soi.id) AS items_count, '
                    . 'COALESCE(SUM(soi.qty), 0) AS items_qty, '
                    . 'COALESCE(SUM(soi.qty * soi.sale_price), 0) AS total_value',
                $joins,
                $searchColumns,
                $search,
                $equalityFilters,
                'so.order_date ' . $direction . ', so.id ' . $direction,
                $limit,
                $offset,
                $likeFilters,
                [],
                'so.id',
                null,
                $operatorFilters,
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list sales orders';
            $result->data = array_map(function ($row) { return SalesOrder::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countAll($userId = null, $filters = [])
    {
        $result = new Result();

        try {
            [$searchColumns, $search, $likeFilters] = $this->orderSearchFilters($filters);

            $inFilters = [];
            $equalityFilters = $this->ownerAndStatusFilters($userId, $filters, $inFilters);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count sales orders';
            $result->data = $this->queryBuilder->countAll(
                'sales_orders',
                'so',
                $this->joins(),
                $searchColumns,
                $search,
                $equalityFilters,
                $likeFilters,
                [],
                [],
                $inFilters
            );
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function create($header, $items)
    {
        $result = new Result();

        try {
            $soId = $this->queryBuilder->insert('sales_orders', [
                'customer_id' => (int) $header['customer_id'],
                'source_warehouse_id' => (int) $header['source_warehouse_id'],
                'status' => (string) $header['status'],
                'order_date' => (string) $header['order_date'],
                'note' => $header['note'] ?? null,
                'created_by' => (int) $header['created_by'],
            ]);

            foreach ($items as $item) {
                $this->queryBuilder->insert('sales_order_items', [
                    'sales_order_id' => $soId,
                    'product_id' => (int) $item['product_id'],
                    'qty' => (int) $item['qty'],
                    'sale_price' => (string) $item['sale_price'],
                ]);
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create sales order';
            $result->data = $soId;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Caller holds the header lock in the existing order transaction.
    public function updateDraft($id, $header, $items)
    {
        $result = new Result();
        try {
            $this->queryBuilder->update('sales_orders', [
                'customer_id' => $header['customer_id'],
                'source_warehouse_id' => $header['source_warehouse_id'],
                'order_date' => $header['order_date'],
                'note' => $header['note'],
            ], ['id' => $id, 'status' => SalesOrder::STATUS_DRAFT]);
            $this->queryBuilder->delete('sales_order_items', ['sales_order_id' => $id]);
            foreach ($items as $item) {
                $this->queryBuilder->insert('sales_order_items', [
                    'sales_order_id' => $id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'sale_price' => $item['sale_price'],
                ]);
            }
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Draft sales order saved.';
            $result->data = $id;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateStatus($id, $status, $extras = [], $expectedStatus = null)
    {
        $result = new Result();

        try {
            $columns = ['status' => $status];

            if (isset($extras['approved_by'])) {
                $columns['approved_by'] = (int) $extras['approved_by'];
            }
            if (isset($extras['approved_at'])) {
                $columns['approved_at'] = (string) $extras['approved_at'];
            }
            if (isset($extras['issued_by'])) {
                $columns['issued_by'] = (int) $extras['issued_by'];
            }
            if (isset($extras['issued_at'])) {
                $columns['issued_at'] = (string) $extras['issued_at'];
            }
            if (isset($extras['cancellation_reason'])) {
                $columns['cancellation_reason'] = (string) $extras['cancellation_reason'];
            }

            $filters = ['id' => $id];
            if ($expectedStatus !== null) {
                $filters['status'] = $expectedStatus;
            }
            $affectedRows = $this->queryBuilder->update('sales_orders', $columns, $filters);
            if ($expectedStatus !== null && $affectedRows !== 1) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'The order status has changed. Refresh the order before trying again.';
                $result->data = null;

                return $result;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update sales order status';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countByStatus($userId = null, $dateFrom = null, $dateTo = null)
    {
        $result = new Result();

        try {
            $filters = [];
            if ($userId !== null) {
                $filters['created_by'] = $userId;
            }
            // Optional order-date window (Sales Dashboard period selector).
            $operatorFilters = [];
            if ($dateFrom !== null) {
                $operatorFilters[] = ['order_date', '>=', $dateFrom];
            }
            if ($dateTo !== null) {
                $operatorFilters[] = ['order_date', '<=', $dateTo];
            }

            $rows = $this->queryBuilder->findAll(
                'sales_orders',
                null,
                'status, COUNT(*) AS cnt',
                [],
                [],
                null,
                $filters,
                null,
                0,
                0,
                [],
                [],
                'status',
                null,
                $operatorFilters
            );

            $counts = [];
            foreach ($rows as $row) {
                $counts[(string) $row['status']] = (int) $row['cnt'];
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count sales orders by status';
            $result->data = $counts;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findForExport($from, $to, $userId = null, $warehouseId = null)
    {
        $result = new Result();

        try {
            $operatorFilters = [
                ['so.order_date', '>=', $from],
                ['so.order_date', '<=', $to],
            ];
            if ($userId !== null) {
                $operatorFilters[] = ['so.created_by', '=', $userId];
            }
            // Reports page "Warehouse Location" filter → the SO's source warehouse.
            if ($warehouseId !== null) {
                $operatorFilters[] = ['so.source_warehouse_id', '=', (int) $warehouseId];
            }

            $rows = $this->queryBuilder->findAll(
                'sales_orders',
                'so',
                "'SO' AS order_type, so.id, so.order_date, so.status, "
                    . "c.name AS party_name, w.name AS warehouse_name, u.name AS creator, "
                    . "COUNT(soi.id) AS items_count, SUM(soi.qty * soi.sale_price) AS total_value",
                [
                    ['type' => 'INNER', 'table' => 'customers', 'alias' => 'c', 'on' => self::JOIN_CUSTOMER],
                    ['type' => 'INNER', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = so.source_warehouse_id'],
                    ['type' => 'INNER', 'table' => 'users', 'alias' => 'u', 'on' => 'u.id = so.created_by'],
                    ['type' => 'LEFT', 'table' => 'sales_order_items', 'alias' => 'soi', 'on' => self::JOIN_ITEMS],
                ],
                [],
                null,
                [],
                'so.order_date DESC',
                0,
                0,
                [],
                [],
                'so.id',
                null,
                $operatorFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to export sales orders';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // ADR-002: must only be called inside an existing Database transaction.
    public function lockForUpdate($id)
    {
        $result = new Result();

        try {
            // status is returned from the locking read itself: it is the only
            // read guaranteed current once the row lock is held (a plain SELECT
            // in the same REPEATABLE READ transaction can see an older snapshot).
            $row = $this->queryBuilder->findOne('sales_orders', null, 'id, status', [], ['id' => $id], [], true);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to lock sales order';
            $result->data = $row === null ? [] : [$row];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Shared by findById()/findAll()/countAll() via QueryBuilder —
    // findForExport() above has a different JOIN (inner + items aggregate)
    // and is deliberately kept separate rather than forced to fit this one.
    private function joins()
    {
        return [
            ['table' => 'customers', 'alias' => 'c', 'on' => self::JOIN_CUSTOMER],
            ['table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = so.source_warehouse_id'],
            ['table' => 'users', 'alias' => 'uc', 'on' => 'uc.id = so.created_by'],
            ['table' => 'users', 'alias' => 'ua', 'on' => 'ua.id = so.approved_by'],
            ['table' => 'users', 'alias' => 'ui', 'on' => 'ui.id = so.issued_by'],
        ];
    }

    public function totalRevenue($userId = null, $dateFrom = null, $dateTo = null)
    {
        $result = new Result();

        try {
            $operatorFilters = [];
            if ($userId !== null) {
                $operatorFilters[] = ['so.created_by', '=', $userId];
            }
            if ($dateFrom !== null) {
                $operatorFilters[] = ['so.order_date', '>=', $dateFrom];
            }
            if ($dateTo !== null) {
                $operatorFilters[] = ['so.order_date', '<=', $dateTo];
            }

            $rows = $this->queryBuilder->findAll(
                'sales_orders',
                'so',
                'COALESCE(SUM(soi.qty * soi.sale_price), 0) AS total_revenue',
                [
                    ['type' => 'LEFT', 'table' => 'sales_order_items', 'alias' => 'soi', 'on' => self::JOIN_ITEMS],
                ],
                [],
                null,
                // Revenue is recognised only when goods have actually left the
                // warehouse: Draft/PendingApproval/Approved/Cancelled do not count.
                ['so.status' => SalesOrder::STATUS_FULFILLED],
                null,
                0,
                0,
                [],
                [],
                null,
                null,
                $operatorFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute total revenue';
            $result->data = (float) ($rows[0]['total_revenue'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = 0.0;
        }

        return $result;
    }

    public function getTopCustomers($userId = null, $limit = 5, $dateFrom = null, $dateTo = null)
    {
        $result = new Result();

        try {
            $operatorFilters = [];
            if ($userId !== null) {
                $operatorFilters[] = ['so.created_by', '=', $userId];
            }
            if ($dateFrom !== null) {
                $operatorFilters[] = ['so.order_date', '>=', $dateFrom];
            }
            if ($dateTo !== null) {
                $operatorFilters[] = ['so.order_date', '<=', $dateTo];
            }

            $rows = $this->queryBuilder->findAll(
                'sales_orders',
                'so',
                'c.name AS customer_name, COALESCE(SUM(soi.qty * soi.sale_price), 0) AS total_value, COUNT(DISTINCT so.id) AS order_count',
                [
                    ['type' => 'INNER', 'table' => 'customers', 'alias' => 'c', 'on' => self::JOIN_CUSTOMER],
                    ['type' => 'LEFT', 'table' => 'sales_order_items', 'alias' => 'soi', 'on' => self::JOIN_ITEMS],
                ],
                [],
                null,
                // Rank customers by realised (Fulfilled) revenue only — consistent
                // with totalRevenue() so Draft/Cancelled orders can't inflate the ranking.
                ['so.status' => SalesOrder::STATUS_FULFILLED],
                'total_value DESC',
                (int) $limit,
                0,
                [],
                [],
                'c.id',
                null,
                $operatorFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list top customers';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = [];
        }

        return $result;
    }

    // Shared by findAll()/countAll(): discrete Order Number / Customer Name
    // fields (own field per column, ANDed) take precedence over the legacy
    // combined 'search' filter (OR across both columns) — same precedence
    // rule as ProductMySQLRepository::findAll()'s sku/productName split.
    // Returns [searchColumns, search, likeFilters] ready to splat into
    // QueryBuilder::findAll()/countAll().
    private function orderSearchFilters($filters)
    {
        $orderNumber = isset($filters['order_number']) && $filters['order_number'] !== '' ? (string) $filters['order_number'] : null;
        $customerName = isset($filters['customer_name']) && $filters['customer_name'] !== '' ? (string) $filters['customer_name'] : null;

        if ($orderNumber !== null && preg_match('/^(?:#|SO-)([0-9]+)$/i', trim($orderNumber), $numberMatch) === 1) {
            $orderNumber = (string) ((int) $numberMatch[1]);
        }
        $likeFilters = [];
        if ($orderNumber !== null) {
            $likeFilters['CAST(so.id AS CHAR)'] = $orderNumber;
        }
        if ($customerName !== null) {
            $likeFilters['c.name'] = $customerName;
        }

        if ($likeFilters !== []) {
            return [[], null, $likeFilters];
        }

        $search = ($filters['search'] ?? '') !== '' ? (string) $filters['search'] : null;

        return [['CAST(so.id AS CHAR)', 'c.name'], $search, []];
    }

    // Shared by findAll()/countAll(): owner scoping (BR-018) AND'd with an
    // optional status filter.
    private function ownerAndStatusFilters($userId, $filters, &$inFilters)
    {
        $result = [];

        if ($userId !== null) {
            $result['so.created_by'] = $userId;
        }

        $statusRaw = $filters['status'] ?? [];
        if (is_array($statusRaw) && count($statusRaw) > 0) {
            $inFilters['so.status'] = $statusRaw;
        } elseif (is_string($statusRaw) && $statusRaw !== '') {
            $result['so.status'] = $statusRaw;
        }

        $warehouseRaw = $filters['warehouse_id'] ?? [];
        if (is_array($warehouseRaw) && count($warehouseRaw) > 0) {
            $inFilters['so.source_warehouse_id'] = array_map('intval', $warehouseRaw);
        } elseif (is_string($warehouseRaw) && $warehouseRaw !== '') {
            $result['so.source_warehouse_id'] = (int) $warehouseRaw;
        }

        return $result;
    }
}
