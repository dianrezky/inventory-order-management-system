<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\PurchaseOrder;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;

class PurchaseOrderMySQLRepository implements PurchaseOrderRepositoryInterface
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
                'purchase_orders',
                'po',
                'po.*, s.name AS supplier_name, w.name AS destination_warehouse_name, u.name AS created_by_name',
                $this->joins(),
                ['po.id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find purchase order';
            $result->data = $row === null ? null : PurchaseOrder::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findAll($status = null, $limit = 0, $offset = 0, $search = null, $sortDirection = 'desc', $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
        $result = new Result();

        try {
            $inFilters = [];
            if ($status !== null && is_array($status) && count($status) > 0) {
                $inFilters['po.status'] = $status;
            }
            if ($warehouseIds !== null && count($warehouseIds) > 0) {
                $inFilters['po.destination_warehouse_id'] = array_map('intval', $warehouseIds);
            }
            $direction = strtoupper((string) $sortDirection) === 'ASC' ? 'ASC' : 'DESC';

            [$searchColumns, $searchTerm, $likeFilters] = $this->orderSearchFilters($search, $orderNumber, $supplierName);

            // Aggregate per-PO item counts/qtys/total so the list view can render
            // "Items / Inbound Progress" + "Total Value" columns (Stitch §12.5 PO list)
            // without N+1 queries.
            $joins = array_merge($this->joins(), [
                ['type' => 'LEFT', 'table' => 'purchase_order_items', 'alias' => 'poi', 'on' => 'poi.purchase_order_id = po.id'],
            ]);

            $rows = $this->queryBuilder->findAll(
                'purchase_orders',
                'po',
                'po.*, s.name AS supplier_name, w.name AS destination_warehouse_name, u.name AS created_by_name, '
                    . 'COUNT(poi.id) AS items_count, '
                    . 'COALESCE(SUM(poi.qty_ordered), 0) AS items_qty_ordered, '
                    . 'COALESCE(SUM(poi.qty_received), 0) AS items_qty_received, '
                    . 'COALESCE(SUM(poi.qty_ordered * poi.purchase_price), 0) AS total_value',
                $joins,
                $searchColumns,
                $searchTerm,
                [],
                'po.order_date ' . $direction . ', po.id ' . $direction,
                $limit,
                $offset,
                $likeFilters,
                [],
                'po.id',
                null,
                [],
                $inFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list purchase orders';
            $result->data = array_map(function ($row) { return PurchaseOrder::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countAll($status = null, $search = null, $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
       $result = new Result();

       try {
           $inFilters = [];
           if ($status !== null && is_array($status) && count($status) > 0) {
               $inFilters['po.status'] = $status;
           }
           if ($warehouseIds !== null && count($warehouseIds) > 0) {
               $inFilters['po.destination_warehouse_id'] = array_map('intval', $warehouseIds);
           }

           [$searchColumns, $searchTerm, $likeFilters] = $this->orderSearchFilters($search, $orderNumber, $supplierName);

           $result->code = Result::CODE_SUCCESS;
           $result->info = 'Success to count purchase orders';
           $result->data = $this->queryBuilder->countAll(
               'purchase_orders',
               'po',
               $this->joins(),
               $searchColumns,
               $searchTerm,
               [],
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

    public function create($data)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('purchase_orders', [
                'supplier_id' => (int) $data['supplier_id'],
                'destination_warehouse_id' => (int) $data['destination_warehouse_id'],
                'status' => (string) $data['status'],
                'order_date' => (string) $data['order_date'],
                'note' => $data['note'] ?? null,
                'created_by' => (int) $data['created_by'],
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create purchase order';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function lockForUpdate($id)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne('purchase_orders', null, 'id, status', [], ['id' => $id], [], true);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to lock purchase order';
            $result->data = $row;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateStatus($id, $status, $expectedStatus = null)
    {
        $result = new Result();

        try {
            $filters = ['id' => $id];
            if ($expectedStatus !== null) {
                $filters['status'] = $expectedStatus;
            }
            $affectedRows = $this->queryBuilder->update('purchase_orders', ['status' => $status], $filters);
            if ($expectedStatus !== null && $affectedRows !== 1) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'The order status has changed. Refresh the order before trying again.';
                $result->data = null;

                return $result;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update purchase order status';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countByStatus()
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'purchase_orders',
                null,
                'status, COUNT(*) AS cnt',
                [],
                [],
                null,
                [],
                null,
                0,
                0,
                [],
                [],
                'status'
            );
            $counts = [];
            foreach ($rows as $row) {
                $counts[(string) $row['status']] = (int) $row['cnt'];
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count purchase orders by status';
            $result->data = $counts;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findForExport($from, $to, $warehouseId = null)
    {
        $result = new Result();

        try {
            $operatorFilters = [
                ['po.order_date', '>=', $from],
                ['po.order_date', '<=', $to],
            ];
            // Reports page "Warehouse Location" filter → the PO's destination warehouse.
            if ($warehouseId !== null) {
                $operatorFilters[] = ['po.destination_warehouse_id', '=', (int) $warehouseId];
            }

            $rows = $this->queryBuilder->findAll(
                'purchase_orders',
                'po',
                "'PO' AS order_type, po.id, po.order_date, po.status, "
                    . "s.name AS party_name, w.name AS warehouse_name, u.name AS creator, "
                    . "COUNT(poi.id) AS items_count, SUM(poi.qty_ordered * poi.purchase_price) AS total_value",
                [
                    ['type' => 'INNER', 'table' => 'suppliers', 'alias' => 's', 'on' => 's.id = po.supplier_id'],
                    ['type' => 'INNER', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = po.destination_warehouse_id'],
                    ['type' => 'INNER', 'table' => 'users', 'alias' => 'u', 'on' => 'u.id = po.created_by'],
                    ['type' => 'LEFT', 'table' => 'purchase_order_items', 'alias' => 'poi', 'on' => 'poi.purchase_order_id = po.id'],
                ],
                [],
                null,
                [],
                'po.order_date DESC',
                0,
                0,
                [],
                [],
                'po.id',
                null,
                $operatorFilters
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to export purchase orders';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Shared by findAll()/countAll(): discrete Order Number / Supplier Name
    // fields (own field per column, ANDed) take precedence over the legacy
    // combined $search (OR across both columns) — same precedence rule as
    // ProductMySQLRepository::findAll()'s sku/productName split. Returns
    // [searchColumns, search, likeFilters] ready to splat into
    // QueryBuilder::findAll()/countAll().
    private function orderSearchFilters($search, $orderNumber, $supplierName)
    {
        $orderNumber = $orderNumber !== null && $orderNumber !== '' ? (string) $orderNumber : null;
        $supplierName = $supplierName !== null && $supplierName !== '' ? (string) $supplierName : null;

        if ($orderNumber !== null && preg_match('/^(?:#|PO-)([0-9]+)$/i', trim($orderNumber), $numberMatch) === 1) {
            $orderNumber = (string) ((int) $numberMatch[1]);
        }
        $likeFilters = [];
        if ($orderNumber !== null) {
            $likeFilters['CAST(po.id AS CHAR)'] = $orderNumber;
        }
        if ($supplierName !== null) {
            $likeFilters['s.name'] = $supplierName;
        }

        if ($likeFilters !== []) {
            return [[], null, $likeFilters];
        }

        return [['CAST(po.id AS CHAR)', 's.name'], $search, []];
    }

    // Shared by findById()/findAll()/countAll() via QueryBuilder —
    // findForExport() above has a different JOIN (inner + items aggregate)
    // and is deliberately kept separate rather than forced to fit this one.
    private function joins()
    {
        return [
            ['table' => 'suppliers', 'alias' => 's', 'on' => 's.id = po.supplier_id'],
            ['table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = po.destination_warehouse_id'],
            ['table' => 'users', 'alias' => 'u', 'on' => 'u.id = po.created_by'],
        ];
    }
}
