<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Repository\Interface\ReportRepositoryInterface;

// Reporting aggregates need derived-table joins, correlated subqueries and
// CASE expressions that QueryBuilder can't compose, so — like
// NotificationMySQLRepository — this repository prepares its SQL directly.
// Every value is bound; the only interpolated fragments are server-built
// (scope/WHERE clauses from integers, allowlisted ORDER BY, cast LIMIT/OFFSET).
// Named placeholders are prefixed per query: native prepared statements
// (EMULATE_PREPARES=false) reject a placeholder used twice.
class ReportMySQLRepository implements ReportRepositoryInterface
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function inventoryValuation($categoryId, $warehouseId)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'ps.warehouse_id', 'k1');
            $row = $this->fetchOne("
                SELECT COALESCE(SUM(ps.quantity * p.purchase_price), 0) AS valuation
                FROM product_stocks ps
                JOIN products p ON p.id = ps.product_id
                WHERE {$scopeSql}
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute inventory valuation';
            $result->data = (int) ($row['valuation'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function skuSummary($categoryId, $warehouseId)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, 0, null, 'k2');
            $stockJoinWh = '';
            if ($warehouseId > 0) {
                $stockJoinWh = ' AND ps.warehouse_id = :k2_jwid';
                $params['k2_jwid'] = (int) $warehouseId;
            }
            $row = $this->fetchOne("
                SELECT COUNT(*) AS active_sku,
                       COALESCE(SUM(t.stock > 0 AND t.stock < t.reorder_point), 0) AS low_sku,
                       COALESCE(SUM(t.stock = 0), 0) AS out_sku
                FROM (
                    SELECT p.id, p.reorder_point, COALESCE(SUM(ps.quantity), 0) AS stock
                    FROM products p
                    LEFT JOIN product_stocks ps ON ps.product_id = p.id{$stockJoinWh}
                    WHERE {$scopeSql}
                    GROUP BY p.id, p.reorder_point
                ) t
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to summarise SKUs';
            $result->data = [
                'active_sku' => (int) ($row['active_sku'] ?? 0),
                'low_sku' => (int) ($row['low_sku'] ?? 0),
                'out_sku' => (int) ($row['out_sku'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function movedSkuCount($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'sl.warehouse_id', 'k2m');
            $params['k2m_df'] = $dateFrom;
            $params['k2m_dt'] = $dateTo;
            $row = $this->fetchOne("
                SELECT COUNT(DISTINCT sl.product_id) AS moved
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE sl.type = 'Issue' AND DATE(sl.done_at) BETWEEN :k2m_df AND :k2m_dt AND {$scopeSql}
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count moved SKUs';
            $result->data = (int) ($row['moved'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function movementTotals($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'sl.warehouse_id', 'k3');
            $params['k3_df'] = $dateFrom;
            $params['k3_dt'] = $dateTo;
            $rows = $this->fetchAll("
                SELECT sl.type, COALESCE(SUM(ABS(sl.qty)), 0) AS total_qty
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE DATE(sl.done_at) BETWEEN :k3_df AND :k3_dt AND {$scopeSql}
                GROUP BY sl.type
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to total stock movements';
            $result->data = $this->totalsByType($rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function outflowValue($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'sl.warehouse_id', 'k4');
            $params['k4_df'] = $dateFrom;
            $params['k4_dt'] = $dateTo;
            $row = $this->fetchOne("
                SELECT COALESCE(SUM(ABS(sl.qty) * p.purchase_price), 0) AS outflow_value
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE sl.type = 'Issue' AND DATE(sl.done_at) BETWEEN :k4_df AND :k4_dt AND {$scopeSql}
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute outflow value';
            $result->data = (int) ($row['outflow_value'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function categoryValuation($categoryId, $warehouseId)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, 0, null, 'cb');
            $joinWh = '';
            if ($warehouseId > 0) {
                $joinWh = ' AND ps.warehouse_id = :cb_jwid';
                $params['cb_jwid'] = (int) $warehouseId;
            }
            $rows = $this->fetchAll("
                SELECT c.id AS category_id, c.name AS category_name,
                       COUNT(DISTINCT p.id) AS sku_count,
                       COALESCE(SUM(ps.quantity), 0) AS total_quantity,
                       COALESCE(SUM(ps.quantity * p.purchase_price), 0) AS total_valuation
                FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN product_stocks ps ON ps.product_id = p.id{$joinWh}
                WHERE {$scopeSql}
                GROUP BY c.id, c.name
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to value categories';
            $result->data = $rows;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function categoryOutflow($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'sl.warehouse_id', 'co');
            $params['co_df'] = $dateFrom;
            $params['co_dt'] = $dateTo;
            $rows = $this->fetchAll("
                SELECT p.category_id, COALESCE(SUM(ABS(sl.qty) * p.purchase_price), 0) AS outflow_value
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE sl.type = 'Issue' AND DATE(sl.done_at) BETWEEN :co_df AND :co_dt AND {$scopeSql}
                GROUP BY p.category_id
            ", $params);

            $outflow = [];
            foreach ($rows as $row) {
                $outflow[(int) $row['category_id']] = (int) $row['outflow_value'];
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to compute category outflow';
            $result->data = $outflow;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function valuationAt($categoryId, $warehouseId, $pointInTime)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, 0, null, 'tv');
            $whStock = '';
            $whLedger = '';
            if ($warehouseId > 0) {
                $whStock = ' WHERE warehouse_id = :tv_swid';
                $whLedger = ' AND warehouse_id = :tv_lwid';
                $params['tv_swid'] = (int) $warehouseId;
                $params['tv_lwid'] = (int) $warehouseId;
            }
            $params['tv_end'] = $pointInTime;
            $row = $this->fetchOne("
                SELECT COALESCE(SUM((COALESCE(s.qty, 0) - COALESCE(l.after_qty, 0)) * p.purchase_price), 0) AS valuation
                FROM products p
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) AS qty FROM product_stocks{$whStock} GROUP BY product_id
                ) s ON s.product_id = p.id
                LEFT JOIN (
                    SELECT product_id, SUM(qty) AS after_qty FROM stock_ledger
                    WHERE done_at > :tv_end{$whLedger} GROUP BY product_id
                ) l ON l.product_id = p.id
                WHERE {$scopeSql}
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to reconstruct valuation';
            $result->data = max(0, (int) ($row['valuation'] ?? 0));
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function monthMovements($categoryId, $warehouseId, $year, $month)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, $warehouseId, 'sl.warehouse_id', 'tm');
            $params['tm_m'] = (int) $month;
            $params['tm_y'] = (int) $year;
            $rows = $this->fetchAll("
                SELECT sl.type, COALESCE(SUM(ABS(sl.qty)), 0) AS total_qty
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE MONTH(sl.done_at) = :tm_m AND YEAR(sl.done_at) = :tm_y AND {$scopeSql}
                GROUP BY sl.type
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to total monthly movements';
            $result->data = $this->totalsByType($rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function warehouseValuation($categoryId, $warehouseId)
    {
        $result = new Result();

        try {
            [$scopeSql, $params] = $this->scope($categoryId, 0, null, 'wa');
            $params['wa_wid'] = (int) $warehouseId;
            $row = $this->fetchOne("
                SELECT COALESCE(SUM(ps.quantity * p.purchase_price), 0) AS valuation
                FROM product_stocks ps
                JOIN products p ON p.id = ps.product_id
                WHERE ps.warehouse_id = :wa_wid AND {$scopeSql}
            ", $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to value warehouse';
            $result->data = (int) ($row['valuation'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Per-SKU stock, valuation, primary warehouse and 30-day outflow — sorted and
    // paginated in SQL across the whole result, all scoped to the chosen warehouse.
    public function stockValuationLines($filters, $sort, $limit, $offset)
    {
        $result = new Result();

        try {
            [$where, $params] = $this->productWhere($filters);
            $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
            $stockWh = '';
            $issueWh = '';
            $hubWh = '';
            // With a warehouse chosen, list only products stocked there.
            $stockJoinType = 'LEFT';
            $rowParams = $params;
            if ($warehouseId > 0) {
                $stockWh = ' WHERE warehouse_id = :st_wid';
                $issueWh = ' AND warehouse_id = :is_wid';
                $hubWh = ' AND ps2.warehouse_id = :hub_wid';
                $params['st_wid'] = $warehouseId;
                $rowParams['st_wid'] = $warehouseId;
                $rowParams['is_wid'] = $warehouseId;
                $rowParams['hub_wid'] = $warehouseId;
                $stockJoinType = 'INNER';
            }
            $stockJoin = "{$stockJoinType} JOIN (
                    SELECT product_id, SUM(quantity) AS qty FROM product_stocks{$stockWh} GROUP BY product_id
                ) st ON st.product_id = p.id";

            $total = (int) ($this->fetchOne("SELECT COUNT(*) AS cnt FROM products p {$stockJoin} WHERE {$where}", $params)['cnt'] ?? 0);

            $orderBy = match ($sort) {
                'valuation_asc' => 'valuation ASC, p.name ASC',
                'velocity_desc' => 'velocity DESC, p.name ASC',
                'name_asc'      => 'p.name ASC',
                default         => 'valuation DESC, p.name ASC',
            };
            $rows = $this->fetchAll("
                SELECT p.id, p.sku, p.name, p.unit, p.purchase_price, p.reorder_point,
                       c.name AS category_name,
                       COALESCE(st.qty, 0) AS total_stock,
                       COALESCE(st.qty, 0) * p.purchase_price AS valuation,
                       COALESCE(iss.outflow, 0) AS outflow_30d,
                       CASE WHEN COALESCE(st.qty, 0) > 0 THEN COALESCE(iss.outflow, 0) / st.qty ELSE 0 END AS velocity,
                       (SELECT w.name FROM product_stocks ps2
                            JOIN warehouses w ON w.id = ps2.warehouse_id
                            WHERE ps2.product_id = p.id AND ps2.quantity > 0{$hubWh}
                            ORDER BY w.is_active DESC, w.code ASC LIMIT 1) AS hub
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$stockJoin}
                LEFT JOIN (
                    SELECT product_id, SUM(ABS(qty)) AS outflow FROM stock_ledger
                    WHERE type = 'Issue'
                      AND DATE(done_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE(){$issueWh}
                    GROUP BY product_id
                ) iss ON iss.product_id = p.id
                WHERE {$where}
                ORDER BY {$orderBy}
                " . $this->limitSql($limit, $offset), $rowParams);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list stock valuation lines';
            $result->data = ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // On-hand stock with its last Receipt date. Stock and receipts are
    // pre-aggregated per product before the join — joining both one-to-many
    // tables directly would fan them out and inflate SUM(quantity).
    public function inventoryAgingLines($filters, $sort, $limit, $offset)
    {
        $result = new Result();

        try {
            [$where, $params] = $this->productWhere($filters);
            $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
            $stockWh = '';
            $ledgerWh = '';
            $rowParams = $params;
            if ($warehouseId > 0) {
                $stockWh = ' WHERE warehouse_id = :wid1';
                $ledgerWh = ' AND warehouse_id = :wid2';
                $params['wid1'] = $warehouseId;
                $rowParams['wid1'] = $warehouseId;
                $rowParams['wid2'] = $warehouseId;
            }
            // Only stock actually on hand has an age.
            $stockJoin = "INNER JOIN (
                    SELECT product_id, SUM(quantity) AS total_stock, MAX(updated_at) AS last_stock_update
                    FROM product_stocks{$stockWh}
                    GROUP BY product_id
                    HAVING SUM(quantity) > 0
                ) stock_agg ON stock_agg.product_id = p.id";

            $total = (int) ($this->fetchOne("SELECT COUNT(*) AS cnt FROM products p {$stockJoin} WHERE {$where}", $params)['cnt'] ?? 0);

            $orderBy = match ($sort) {
                'valuation_desc' => 'stock_agg.total_stock * p.purchase_price DESC, p.name ASC',
                'valuation_asc'  => 'stock_agg.total_stock * p.purchase_price ASC, p.name ASC',
                'name_asc'       => 'p.name ASC',
                default          => 'COALESCE(receipt_agg.last_receipt_at, stock_agg.last_stock_update) ASC, p.name ASC',
            };
            $rows = $this->fetchAll("
                SELECT p.id, p.sku, p.name, p.unit, p.purchase_price,
                       c.name AS category_name,
                       COALESCE(stock_agg.total_stock, 0) AS total_stock,
                       stock_agg.last_stock_update,
                       receipt_agg.last_receipt_at
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$stockJoin}
                LEFT JOIN (
                    SELECT product_id, MAX(done_at) AS last_receipt_at
                    FROM stock_ledger
                    WHERE type = 'Receipt'{$ledgerWh}
                    GROUP BY product_id
                ) receipt_agg ON receipt_agg.product_id = p.id
                WHERE {$where}
                ORDER BY {$orderBy}
                " . $this->limitSql($limit, $offset), $rowParams);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list inventory aging lines';
            $result->data = ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // SKUs still holding stock with zero or under-1.0x 30-day sell-through.
    public function slowMovingLines($filters, $sort, $limit, $offset)
    {
        $result = new Result();

        try {
            [$where, $params] = $this->productWhere($filters);
            $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
            $stockWh = '';
            $ledgerWh = '';
            if ($warehouseId > 0) {
                $stockWh = ' WHERE warehouse_id = :wid1';
                $ledgerWh = ' AND warehouse_id = :wid2';
                $params['wid1'] = $warehouseId;
                $params['wid2'] = $warehouseId;
            }
            $baseSql = "
                SELECT p.id, p.sku, p.name, p.unit, p.purchase_price,
                       c.name AS category_name,
                       COALESCE(stock_agg.total_stock, 0) AS total_stock,
                       COALESCE(issue_agg.outflow_30d, 0) AS outflow_30d
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) AS total_stock
                    FROM product_stocks{$stockWh}
                    GROUP BY product_id
                ) stock_agg ON stock_agg.product_id = p.id
                LEFT JOIN (
                    SELECT product_id, SUM(ABS(qty)) AS outflow_30d
                    FROM stock_ledger
                    WHERE type = 'Issue'
                      AND DATE(done_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE(){$ledgerWh}
                    GROUP BY product_id
                ) issue_agg ON issue_agg.product_id = p.id
                WHERE {$where}
                HAVING total_stock > 0 AND (outflow_30d = 0 OR outflow_30d < total_stock)
            ";

            $total = (int) ($this->fetchOne("SELECT COUNT(*) AS cnt FROM ({$baseSql}) slow", $params)['cnt'] ?? 0);

            $orderBy = match ($sort) {
                'valuation_desc' => 'total_stock * purchase_price DESC, name ASC',
                'valuation_asc'  => 'total_stock * purchase_price ASC, name ASC',
                'name_asc'       => 'name ASC',
                default          => 'outflow_30d / total_stock ASC, total_stock DESC, name ASC',
            };
            $rows = $this->fetchAll("{$baseSql} ORDER BY {$orderBy} " . $this->limitSql($limit, $offset), $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list slow-moving lines';
            $result->data = ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function movementLedgerLines($filters, $sort, $limit, $offset)
    {
        $result = new Result();

        try {
            $where = ['DATE(sl.done_at) BETWEEN :df AND :dt'];
            $params = ['df' => (string) ($filters['date_from'] ?? ''), 'dt' => (string) ($filters['date_to'] ?? '')];
            $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
            $categoryId = (int) ($filters['category_id'] ?? 0);
            $search = trim((string) ($filters['search'] ?? ''));
            if ($warehouseId > 0) {
                $where[] = 'sl.warehouse_id = :wid';
                $params['wid'] = $warehouseId;
            }
            if ($categoryId > 0) {
                $where[] = 'p.category_id = :cid';
                $params['cid'] = $categoryId;
            }
            if ($search !== '') {
                $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
                $params['q1'] = '%' . $search . '%';
                $params['q2'] = '%' . $search . '%';
            }
            $whereSql = implode(' AND ', $where);

            $total = (int) ($this->fetchOne("
                SELECT COUNT(*) AS cnt
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                WHERE {$whereSql}
            ", $params)['cnt'] ?? 0);

            $orderBy = match ($sort) {
                'date_asc' => 'sl.done_at ASC, sl.id ASC',
                'name_asc' => 'p.name ASC, sl.done_at DESC',
                default    => 'sl.done_at DESC, sl.id DESC',
            };
            $rows = $this->fetchAll("
                SELECT sl.done_at, sl.type, sl.qty, sl.ref_type, sl.ref_id,
                       p.sku, p.name AS product_name,
                       w.name AS warehouse_name,
                       u.name AS done_by_name
                FROM stock_ledger sl
                JOIN products p ON p.id = sl.product_id
                LEFT JOIN warehouses w ON w.id = sl.warehouse_id
                LEFT JOIN users u ON u.id = sl.done_by_user_id
                WHERE {$whereSql}
                ORDER BY {$orderBy}
                " . $this->limitSql($limit, $offset), $params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list stock ledger lines';
            $result->data = ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // WHERE fragment for the page-wide scope: active products, optional
    // category, optional warehouse on $warehouseColumn.
    private function scope($categoryId, $warehouseId, $warehouseColumn, $prefix)
    {
        $where = ['p.is_active = 1'];
        $params = [];
        if ((int) $categoryId > 0) {
            $where[] = "p.category_id = :{$prefix}_cid";
            $params["{$prefix}_cid"] = (int) $categoryId;
        }
        if ((int) $warehouseId > 0 && $warehouseColumn !== null) {
            $where[] = "{$warehouseColumn} = :{$prefix}_wid";
            $params["{$prefix}_wid"] = (int) $warehouseId;
        }

        return [implode(' AND ', $where), $params];
    }

    // Product-level WHERE for the line-item tables: active, category, search.
    private function productWhere($filters)
    {
        $where = ['p.is_active = 1'];
        $params = [];
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));
        if ($categoryId > 0) {
            $where[] = 'p.category_id = :cid';
            $params['cid'] = $categoryId;
        }
        if ($search !== '') {
            $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
            $params['q1'] = '%' . $search . '%';
            $params['q2'] = '%' . $search . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function limitSql($limit, $offset)
    {
        return 'LIMIT ' . max(0, (int) $limit) . ' OFFSET ' . max(0, (int) $offset);
    }

    private function totalsByType($rows)
    {
        $totals = ['Receipt' => 0, 'Issue' => 0];
        foreach ($rows as $row) {
            if (array_key_exists($row['type'], $totals)) {
                $totals[$row['type']] += (int) $row['total_qty'];
            }
        }

        return $totals;
    }

    private function fetchOne($sql, $params)
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    private function fetchAll($sql, $params)
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
