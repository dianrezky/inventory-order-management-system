<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\ReportRepositoryInterface;

// Business rules behind the /reports dashboard: which report types and sorts
// exist, how the date range is defaulted, how turnover/aging/slow-moving are
// classified, and how the page-wide scope (warehouse + category) is applied to
// every KPI, chart and table. All figures come from ReportRepositoryInterface
// aggregates over product_stocks / stock_ledger (ALUR-03: ledger is the source
// of truth), never from cached or static numbers.
class ReportService
{
    public const PER_PAGE = 5;
    public const DEFAULT_REPORT_TYPE = 'stock_valuation';

    // Sorts each report type can actually apply (first = default). The sort
    // select offers only these; anything else posted falls back to the default.
    public const SORT_OPTIONS = [
        'stock_valuation' => [
            'valuation_desc' => 'Valuation (High-Low)',
            'valuation_asc'  => 'Valuation (Low-High)',
            'velocity_desc'  => 'Velocity (Fastest First)',
            'name_asc'       => 'Product Name (A-Z)',
        ],
        'inventory_aging' => [
            'aging_desc'     => 'Oldest Stock First',
            'valuation_desc' => 'Valuation (High-Low)',
            'valuation_asc'  => 'Valuation (Low-High)',
            'name_asc'       => 'Product Name (A-Z)',
        ],
        'slow_moving' => [
            'velocity_asc'   => 'Slowest First',
            'valuation_desc' => 'Valuation (High-Low)',
            'valuation_asc'  => 'Valuation (Low-High)',
            'name_asc'       => 'Product Name (A-Z)',
        ],
        'movement_ledger' => [
            'date_desc'      => 'Newest First',
            'date_asc'       => 'Oldest First',
            'name_asc'       => 'Product Name (A-Z)',
        ],
    ];

    // Threshold conventions of this dashboard-only report (PROJECT_REFERENCE.md
    // defines the report types only through the page's tooltip, not numbers).
    private const HIGH_VELOCITY_30D = 5.0;
    private const AGING_DAYS_CRITICAL = 90;
    private const AGING_DAYS_STALE = 60;
    private const AGING_DAYS_AGING = 30;

    private $reportRepository;

    public function __construct(ReportRepositoryInterface $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    // Normalises the raw POSTed filter form into a safe, allowlisted set.
    // Default date range = the last 30 days, which is what the filter card's
    // "DEFAULT: SNAPSHOT LAST 30 DAYS" label promises.
    public function normalizeParams($raw)
    {
        $reportType = trim((string) ($raw['report_type'] ?? self::DEFAULT_REPORT_TYPE));
        if (!array_key_exists($reportType, self::SORT_OPTIONS)) {
            $reportType = self::DEFAULT_REPORT_TYPE;
        }

        $dateFrom = $this->validDate($raw['date_from'] ?? null, date('Y-m-d', strtotime('-29 days')));
        $dateTo = $this->validDate($raw['date_to'] ?? null, date('Y-m-d'));
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $sortOptions = self::SORT_OPTIONS[$reportType];
        $sort = trim((string) ($raw['sort'] ?? ''));
        if (!array_key_exists($sort, $sortOptions)) {
            $sort = array_key_first($sortOptions);
        }

        return [
            'report_type'  => $reportType,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'warehouse_id' => max(0, (int) ($raw['warehouse_id'] ?? 0)),
            'category_id'  => max(0, (int) ($raw['category_id'] ?? 0)),
            'search'       => trim((string) ($raw['q'] ?? '')),
            'sort'         => $sort,
            'sort_options' => $sortOptions,
            'page'         => max(1, (int) ($raw['page'] ?? 1)),
        ];
    }

    // Every KPI, chart and breakdown is scoped by warehouse + category, same as
    // the table (the table's own search box narrows only the table).
    // $warehouses: the active warehouses shown in the allocation panel.
    public function getDashboard($params, $warehouses)
    {
        $categoryId = $params['category_id'];
        $warehouseId = $params['warehouse_id'];
        $dateFrom = $params['date_from'];
        $dateTo = $params['date_to'];

        $valuationResult = $this->reportRepository->inventoryValuation($categoryId, $warehouseId);
        $totalValuation = (int) $this->dataOf($valuationResult, 0);

        $skuResult = $this->reportRepository->skuSummary($categoryId, $warehouseId);
        $sku = $this->dataOf($skuResult, ['active_sku' => 0, 'low_sku' => 0, 'out_sku' => 0]);

        $movedResult = $this->reportRepository->movedSkuCount($categoryId, $warehouseId, $dateFrom, $dateTo);
        $movementResult = $this->reportRepository->movementTotals($categoryId, $warehouseId, $dateFrom, $dateTo);
        $movements = $this->dataOf($movementResult, ['Receipt' => 0, 'Issue' => 0]);

        // Turnover: outflow at cost over the range ÷ on-hand valuation, annualised
        // so it compares to the card's ">4.0x / yr" benchmark.
        $outflowResult = $this->reportRepository->outflowValue($categoryId, $warehouseId, $dateFrom, $dateTo);
        $outflowValue = (int) $this->dataOf($outflowResult, 0);
        $rangeDays = max(1, (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);

        [$categoryData, $categoryTotal] = $this->categoryBreakdown($categoryId, $warehouseId, $dateFrom, $dateTo, $rangeDays);
        [$whData, $whGrandTotal] = $this->warehouseAllocation($categoryId, $warehouseId, $warehouses);
        [$lineItems, $lineItemsTotal] = $this->lineItems($params);

        return [
            'totalValuation'   => $totalValuation,
            'activeSku'        => (int) $sku['active_sku'],
            'lowSku'           => (int) $sku['low_sku'],
            'outSku'           => (int) $sku['out_sku'],
            'movedSku'         => (int) $this->dataOf($movedResult, 0),
            'inboundQty'       => (int) $movements['Receipt'],
            'outboundQty'      => (int) $movements['Issue'],
            'netQty'           => (int) $movements['Receipt'] - (int) $movements['Issue'],
            'turnoverVelocity' => $this->annualisedTurnover($outflowValue, $totalValuation, $rangeDays),
            'categoryData'     => $categoryData,
            'categoryTotal'    => $categoryTotal,
            'trendData'        => $this->trend($categoryId, $warehouseId),
            'whData'           => $whData,
            'whGrandTotal'     => $whGrandTotal,
            'lineItems'        => $lineItems,
            'lineItemsTotal'   => $lineItemsTotal,
            'totalPages'       => max(1, (int) ceil($lineItemsTotal / self::PER_PAGE)),
            'perPage'          => self::PER_PAGE,
        ];
    }

    private function categoryBreakdown($categoryId, $warehouseId, $dateFrom, $dateTo, $rangeDays)
    {
        $valuationResult = $this->reportRepository->categoryValuation($categoryId, $warehouseId);
        $rows = $this->dataOf($valuationResult, []);
        $outflowResult = $this->reportRepository->categoryOutflow($categoryId, $warehouseId, $dateFrom, $dateTo);
        $outflow = $this->dataOf($outflowResult, []);

        $total = (int) array_sum(array_column($rows, 'total_valuation'));
        $categoryData = [];
        foreach ($rows as $row) {
            $valuation = (int) ($row['total_valuation'] ?? 0);
            $categoryData[] = [
                'name'       => $row['category_name'] ?? 'Uncategorized',
                'sku_count'  => (int) ($row['sku_count'] ?? 0),
                'quantity'   => (int) ($row['total_quantity'] ?? 0),
                'valuation'  => $valuation,
                // Real per-category turnover, same formula as the KPI card.
                'velocity'   => $this->annualisedTurnover((int) ($outflow[(int) $row['category_id']] ?? 0), $valuation, $rangeDays),
                'percentage' => $total > 0 ? round($valuation / $total * 100, 1) : 0,
            ];
        }
        usort($categoryData, static fn($a, $b) => $b['valuation'] <=> $a['valuation']);

        return [$categoryData, $total];
    }

    // Six month-end points. Valuation is reconstructed from the Stock Ledger
    // (stock now − net movements after the month end), not a repeated snapshot.
    private function trend($categoryId, $warehouseId)
    {
        $trendData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = strtotime(date('Y-m-01', strtotime("-$i month")));
            $monthEnd = $i === 0 ? date('Y-m-d H:i:s') : date('Y-m-t 23:59:59', $monthStart);

            $valuationResult = $this->reportRepository->valuationAt($categoryId, $warehouseId, $monthEnd);
            $movementResult = $this->reportRepository->monthMovements($categoryId, $warehouseId, (int) date('Y', $monthStart), (int) date('m', $monthStart));
            $movements = $this->dataOf($movementResult, ['Receipt' => 0, 'Issue' => 0]);

            $trendData[] = [
                'label'     => date('M Y', $monthStart),
                'valuation' => (int) $this->dataOf($valuationResult, 0),
                'inbound'   => (int) $movements['Receipt'],
                'outbound'  => (int) $movements['Issue'],
            ];
        }

        return $trendData;
    }

    private function warehouseAllocation($categoryId, $warehouseId, $warehouses)
    {
        $whData = [];
        $grandTotal = 0;
        foreach ($warehouses as $warehouse) {
            if ($warehouseId > 0 && $warehouse->id !== $warehouseId) {
                continue;
            }
            $valuationResult = $this->reportRepository->warehouseValuation($categoryId, $warehouse->id);
            $valuation = (int) $this->dataOf($valuationResult, 0);
            $grandTotal += $valuation;
            $whData[$warehouse->id] = [
                'name'      => $warehouse->name,
                'location'  => $warehouse->location ?? '',
                'valuation' => $valuation,
            ];
        }

        return [$whData, $grandTotal];
    }

    // The paged table; its shape depends on the report type.
    private function lineItems($params)
    {
        $filters = [
            'category_id'  => $params['category_id'],
            'warehouse_id' => $params['warehouse_id'],
            'search'       => $params['search'],
            'date_from'    => $params['date_from'],
            'date_to'      => $params['date_to'],
        ];
        $offset = ($params['page'] - 1) * self::PER_PAGE;
        $type = $params['report_type'];

        if ($type === 'inventory_aging') {
            $linesResult = $this->reportRepository->inventoryAgingLines($filters, $params['sort'], self::PER_PAGE, $offset);
            $mapper = [$this, 'agingItem'];
        } elseif ($type === 'slow_moving') {
            $linesResult = $this->reportRepository->slowMovingLines($filters, $params['sort'], self::PER_PAGE, $offset);
            $mapper = [$this, 'slowMovingItem'];
        } elseif ($type === 'movement_ledger') {
            $linesResult = $this->reportRepository->movementLedgerLines($filters, $params['sort'], self::PER_PAGE, $offset);
            $mapper = [$this, 'ledgerItem'];
        } else {
            $linesResult = $this->reportRepository->stockValuationLines($filters, $params['sort'], self::PER_PAGE, $offset);
            $mapper = [$this, 'stockValuationItem'];
        }
        $page = $this->dataOf($linesResult, ['rows' => [], 'total' => 0]);

        return [array_map($mapper, $page['rows']), (int) $page['total']];
    }

    private function stockValuationItem($row)
    {
        $totalStock = (int) $row['total_stock'];
        $velocity30d = round((float) $row['velocity'], 1);

        $status = 'In Stock';
        $statusType = 'in_stock';
        if ($totalStock === 0) {
            $status = 'Out of Stock';
            $statusType = 'out_of_stock';
        } elseif ($totalStock < (int) $row['reorder_point']) {
            $status = 'Low Stock';
            $statusType = 'low_stock';
        } elseif ($velocity30d >= self::HIGH_VELOCITY_30D) {
            $status = 'High Velocity';
            $statusType = 'high_velocity';
        }

        return [
            'sku'         => $row['sku'],
            'name'        => $row['name'],
            'category'    => $row['category_name'] ?? '-',
            'warehouse'   => $row['hub'] ?? '-',
            'stock'       => $totalStock,
            'unit'        => $row['unit'],
            'unit_cost'   => (int) $row['purchase_price'],
            'valuation'   => (int) $row['valuation'],
            'velocity'    => $velocity30d,
            'status'      => $status,
            'status_type' => $statusType,
        ];
    }

    // Aging = days since the most recent Receipt (or, with no recorded receipt,
    // since the stock row was last updated).
    private function agingItem($row)
    {
        $totalStock = (int) $row['total_stock'];
        $referenceDate = $row['last_receipt_at'] ?: $row['last_stock_update'];
        $agingDays = null;
        $agingLabel = 'N/A';
        if ($referenceDate !== null) {
            $agingDays = (int) floor((time() - strtotime($referenceDate)) / 86400);
            $agingLabel = $agingDays . ' days';
        }

        $status = 'Fresh';
        $statusType = 'in_stock';
        if ($agingDays === null) {
            $status = 'Unknown';
            $statusType = 'neutral';
        } elseif ($agingDays > self::AGING_DAYS_CRITICAL) {
            $status = 'Critical';
            $statusType = 'out_of_stock';
        } elseif ($agingDays > self::AGING_DAYS_STALE) {
            $status = 'Stale';
            $statusType = 'low_stock';
        } elseif ($agingDays > self::AGING_DAYS_AGING) {
            $status = 'Aging';
            $statusType = 'high_velocity';
        }

        $purchasePrice = (int) $row['purchase_price'];

        return [
            'sku'          => $row['sku'],
            'name'         => $row['name'],
            'category'     => $row['category_name'] ?? '-',
            'stock'        => $totalStock,
            'unit'         => $row['unit'],
            'unit_cost'    => $purchasePrice,
            'valuation'    => $totalStock * $purchasePrice,
            'last_receipt' => $referenceDate !== null ? date('M d, Y', strtotime($referenceDate)) : '-',
            'aging'        => $agingLabel,
            'status'       => $status,
            'status_type'  => $statusType,
        ];
    }

    // Zero 30-day outflow = Dead Stock; any smaller-than-stock outflow = Slow-Moving.
    private function slowMovingItem($row)
    {
        $totalStock = (int) $row['total_stock'];
        $outflow30d = (int) $row['outflow_30d'];
        $purchasePrice = (int) $row['purchase_price'];

        $status = 'Slow-Moving';
        $statusType = 'low_stock';
        if ($outflow30d === 0) {
            $status = 'Dead Stock';
            $statusType = 'out_of_stock';
        }

        return [
            'sku'         => $row['sku'],
            'name'        => $row['name'],
            'category'    => $row['category_name'] ?? '-',
            'stock'       => $totalStock,
            'unit'        => $row['unit'],
            'unit_cost'   => $purchasePrice,
            'valuation'   => $totalStock * $purchasePrice,
            'outflow_30d' => $outflow30d,
            'velocity'    => $totalStock > 0 ? round($outflow30d / $totalStock, 1) : 0,
            'status'      => $status,
            'status_type' => $statusType,
        ];
    }

    private function ledgerItem($row)
    {
        $qty = (int) $row['qty'];

        return [
            'date'        => date('M d, Y H:i', strtotime($row['done_at'])),
            'sku'         => $row['sku'],
            'name'        => $row['product_name'],
            'warehouse'   => $row['warehouse_name'] ?? '-',
            'type'        => $row['type'],
            'qty'         => $qty,
            'qty_display' => ($qty >= 0 ? '+' : '') . number_format($qty, 0, ',', '.'),
            'ref'         => ($row['ref_type'] ?: '-') . ($row['ref_id'] ? ' #' . $row['ref_id'] : ''),
            'done_by'     => $row['done_by_name'] ?? '-',
            'status'      => $row['type'],
            'status_type' => $row['type'] === 'Receipt' ? 'in_stock' : 'low_stock',
        ];
    }

    private function annualisedTurnover($outflowValue, $valuation, $rangeDays)
    {
        if ($valuation <= 0) {
            return 0.0;
        }

        return round(($outflowValue / $valuation) * (365 / $rangeDays), 1);
    }

    // A Y-m-d date, or $default when missing/malformed.
    private function validDate($value, $default)
    {
        $value = trim((string) $value);
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return $default;
        }

        return $value;
    }

    // A repository failure degrades that one figure to $default (the failure
    // itself is already logged by the repository) instead of breaking the page.
    private function dataOf($result, $default)
    {
        if ($result->code !== Result::CODE_SUCCESS || $result->data === null) {
            return $default;
        }

        return $result->data;
    }
}
