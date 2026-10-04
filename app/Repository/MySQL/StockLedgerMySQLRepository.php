<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\StockLedgerEntry;
use App\Repository\Interface\StockLedgerRepositoryInterface;

class StockLedgerMySQLRepository implements StockLedgerRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function insert($data)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('stock_ledger', [
                'product_id' => (int) $data['product_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'type' => (string) $data['type'],
                'qty' => (int) $data['qty'],
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'note' => $data['note'] ?? null,
                'done_by_user_id' => (int) $data['done_by_user_id'],
                'done_at' => $data['done_at'] ?? date('Y-m-d H:i:s'),
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to insert stock ledger entry';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function listByProductWarehouse($productId, $warehouseId)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'stock_ledger',
                null,
                '*',
                [],
                [],
                null,
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                'done_at ASC, id ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list stock ledger entries';
            $result->data = array_map(function ($row) { return StockLedgerEntry::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function sumByProductWarehouse($productId, $warehouseId)
    {
        $result = new Result();

        try {
            $value = $this->queryBuilder->scalar(
                'stock_ledger',
                null,
                'COALESCE(SUM(qty), 0)',
                [],
                ['product_id' => $productId, 'warehouse_id' => $warehouseId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to sum stock ledger entries';
            $result->data = (int) $value;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findFiltered($filters, $limit, $offset)
    {
        $result = new Result();

        try {
            $equalityFilters = [];
            $inFilters = [];

            // movement_type: array → IN clause, scalar → equality
            $mtRaw = $filters['movement_type'] ?? [];
            if (is_array($mtRaw) && count($mtRaw) > 0) {
                $inFilters['sl.type'] = $mtRaw;
            } elseif (is_string($mtRaw) && $mtRaw !== '') {
                $equalityFilters['sl.type'] = $mtRaw;
            }

            // warehouse_id: array → IN clause, scalar → equality
            $whRaw = $filters['warehouse_id'] ?? [];
            if (is_array($whRaw) && count($whRaw) > 0) {
                $inFilters['sl.warehouse_id'] = array_map('intval', $whRaw);
            } elseif (is_string($whRaw) && $whRaw !== '') {
                $equalityFilters['sl.warehouse_id'] = (int) $whRaw;
            }

            $likeFilters = [];
            if (($filters['sku'] ?? '') !== '') {
                $likeFilters['p.sku'] = $filters['sku'];
            }
            if (($filters['product_name'] ?? '') !== '') {
                $likeFilters['p.name'] = $filters['product_name'];
            }

            $sortDir = ($filters['sort_dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

            $sortColMap = [
                'date' => 'sl.done_at',
                'product' => 'p.name',
                'sku' => 'p.sku',
                'type' => 'sl.type',
                'qty' => 'sl.qty',
                'warehouse' => 'w.name',
            ];
            $sortCol = $sortColMap[$filters['sort_col'] ?? ''] ?? 'sl.done_at';

            $countJoins = [
                ['type' => 'INNER', 'table' => 'products', 'alias' => 'p', 'on' => 'sl.product_id = p.id'],
                ['type' => 'LEFT', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'sl.warehouse_id = w.id'],
            ];

            $total = $this->queryBuilder->countAll('stock_ledger', 'sl', $countJoins, [], null, $equalityFilters, $likeFilters, [], [], $inFilters);

            $dataJoins = array_merge($countJoins, [
                ['type' => 'LEFT', 'table' => 'users', 'alias' => 'u', 'on' => 'sl.done_by_user_id = u.id'],
            ]);

            $columns = 'sl.id, sl.product_id, sl.warehouse_id, sl.type, sl.qty, sl.ref_type, sl.ref_id,'
                . ' sl.note, sl.done_by_user_id, sl.done_at, p.sku, p.name AS product_name,'
                . ' w.name AS warehouse_name, u.name AS user_name,'
                // ALUR-03 "kuantitas sebelum & sesudah": running ledger balance of
                // this product+warehouse up to and including this entry (same
                // done_at ordered by id); qty_before = qty_after - qty in the view.
                . ' (SELECT COALESCE(SUM(sl2.qty), 0) FROM stock_ledger sl2'
                . ' WHERE sl2.product_id = sl.product_id AND sl2.warehouse_id = sl.warehouse_id'
                . ' AND (sl2.done_at < sl.done_at OR (sl2.done_at = sl.done_at AND sl2.id <= sl.id))) AS qty_after';

            $rows = $this->queryBuilder->findAll(
                'stock_ledger',
                'sl',
                $columns,
                $dataJoins,
                [],
                null,
                $equalityFilters,
                $sortCol . ' ' . $sortDir . ', sl.id DESC',
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
            $result->info = 'Success to filter stock ledger entries';
            $result->data = [$rows, $total];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findForExport($from, $to, $warehouseId = null, $categoryId = null, $search = null)
    {
        $result = new Result();

        try {
            // Same scope as the Reports page the export is launched from:
            // warehouse, category and the table's product search (SKU / name).
            $equalityFilters = $warehouseId !== null ? ['sl.warehouse_id' => (int) $warehouseId] : [];
            if ($categoryId !== null) {
                $equalityFilters['p.category_id'] = (int) $categoryId;
            }
            $searchColumns = [];
            $searchTerm = null;
            if ($search !== null && $search !== '') {
                $searchColumns = ['p.sku', 'p.name'];
                $searchTerm = $search;
            }

            $rows = $this->queryBuilder->findAll(
                'stock_ledger',
                'sl',
                'sl.*, p.name AS product_name, p.sku AS product_sku, w.name AS warehouse_name, u.name AS user_name',
                [
                    ['type' => 'INNER', 'table' => 'products', 'alias' => 'p', 'on' => 'p.id = sl.product_id'],
                    ['type' => 'INNER', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = sl.warehouse_id'],
                    ['type' => 'INNER', 'table' => 'users', 'alias' => 'u', 'on' => 'u.id = sl.done_by_user_id'],
                ],
                $searchColumns,
                $searchTerm,
                $equalityFilters,
                'sl.done_at DESC',
                // No cap: a 5000-row limit silently truncated the CSV for longer
                // ranges — an audit export must contain every movement asked for
                // (same as the Orders export).
                0,
                0,
                [],
                [],
                null,
                null,
                [
                    ['sl.done_at', '>=', $from . ' 00:00:00'],
                    ['sl.done_at', '<=', $to . ' 23:59:59'],
                ]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to export stock ledger entries';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findByReference($refType, $refId, $limit = 50)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'stock_ledger',
                'sl',
                'sl.*, p.name AS product_name, p.sku, w.name AS warehouse_name, u.name AS user_name',
                [
                    ['type' => 'INNER', 'table' => 'products',   'alias' => 'p', 'on' => 'p.id = sl.product_id'],
                    ['type' => 'INNER', 'table' => 'warehouses', 'alias' => 'w', 'on' => 'w.id = sl.warehouse_id'],
                    ['type' => 'INNER', 'table' => 'users',      'alias' => 'u', 'on' => 'u.id = sl.done_by_user_id'],
                ],
                [],
                null,
                ['ref_type' => $refType, 'ref_id' => (int) $refId],
                'sl.done_at DESC, sl.id DESC',
                (int) $limit
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list ledger entries by reference';
            $result->data = array_map(function ($row) { return StockLedgerEntry::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
