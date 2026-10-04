<?php

namespace App\Repository\Interface;

// Read-only aggregates behind the /reports dashboard. Every method returns a
// Result whose data is described below. $categoryId / $warehouseId of 0 mean
// "all"; every figure covers active products only.
interface ReportRepositoryInterface
{
    // data: int — on-hand valuation at purchase cost.
    public function inventoryValuation($categoryId, $warehouseId);

    // data: ['active_sku' => int, 'low_sku' => int, 'out_sku' => int].
    public function skuSummary($categoryId, $warehouseId);

    // data: int — distinct products with an Issue movement in [$dateFrom, $dateTo].
    public function movedSkuCount($categoryId, $warehouseId, $dateFrom, $dateTo);

    // data: ['Receipt' => int, 'Issue' => int] — absolute quantities in range.
    public function movementTotals($categoryId, $warehouseId, $dateFrom, $dateTo);

    // data: int — Issue quantity × purchase price in range.
    public function outflowValue($categoryId, $warehouseId, $dateFrom, $dateTo);

    // data: list of ['category_id', 'category_name', 'sku_count', 'total_quantity', 'total_valuation'].
    public function categoryValuation($categoryId, $warehouseId);

    // data: [category_id => int outflow value] for Issue movements in range.
    public function categoryOutflow($categoryId, $warehouseId, $dateFrom, $dateTo);

    // data: int — valuation as of $pointInTime ('Y-m-d H:i:s'), reconstructed
    // from current stock minus the net ledger movements after that moment.
    public function valuationAt($categoryId, $warehouseId, $pointInTime);

    // data: ['Receipt' => int, 'Issue' => int] for one calendar month.
    public function monthMovements($categoryId, $warehouseId, $year, $month);

    // data: int — on-hand valuation held in one warehouse.
    public function warehouseValuation($categoryId, $warehouseId);

    // Paged line items. $filters: category_id, warehouse_id, search, and for
    // the ledger also date_from/date_to. $sort is already allowlisted by the
    // service. data: ['rows' => list<array>, 'total' => int].
    public function stockValuationLines($filters, $sort, $limit, $offset);

    public function inventoryAgingLines($filters, $sort, $limit, $offset);

    public function slowMovingLines($filters, $sort, $limit, $offset);

    public function movementLedgerLines($filters, $sort, $limit, $offset);
}
