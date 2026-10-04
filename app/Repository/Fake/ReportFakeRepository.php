<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Repository\Interface\ReportRepositoryInterface;

// In-memory stand-in for ReportMySQLRepository. Aggregates are canned values
// that tests set through the public properties; line-item methods page the
// canned rows. Scope arguments are recorded in $calls, not applied.
class ReportFakeRepository implements ReportRepositoryInterface
{
    public $valuation = 0;
    public $skuSummary = ['active_sku' => 0, 'low_sku' => 0, 'out_sku' => 0];
    public $movedSku = 0;
    public $movementTotals = ['Receipt' => 0, 'Issue' => 0];
    public $outflowValue = 0;
    public $categoryRows = [];
    public $categoryOutflow = [];
    public $valuationByMonth = [];
    public $warehouseValuations = [];
    public $lines = ['stock_valuation' => [], 'inventory_aging' => [], 'slow_moving' => [], 'movement_ledger' => []];
    public $calls = [];

    public function inventoryValuation($categoryId, $warehouseId)
    {
        $this->calls[] = ['inventoryValuation', $categoryId, $warehouseId];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute inventory valuation';
        $result->data = $this->valuation;

        return $result;
    }

    public function skuSummary($categoryId, $warehouseId)
    {
        $this->calls[] = ['skuSummary', $categoryId, $warehouseId];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to summarise SKUs';
        $result->data = $this->skuSummary;

        return $result;
    }

    public function movedSkuCount($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $this->calls[] = ['movedSkuCount', $categoryId, $warehouseId, $dateFrom, $dateTo];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count moved SKUs';
        $result->data = $this->movedSku;

        return $result;
    }

    public function movementTotals($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $this->calls[] = ['movementTotals', $categoryId, $warehouseId, $dateFrom, $dateTo];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to total stock movements';
        $result->data = $this->movementTotals;

        return $result;
    }

    public function outflowValue($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $this->calls[] = ['outflowValue', $categoryId, $warehouseId, $dateFrom, $dateTo];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute outflow value';
        $result->data = $this->outflowValue;

        return $result;
    }

    public function categoryValuation($categoryId, $warehouseId)
    {
        $this->calls[] = ['categoryValuation', $categoryId, $warehouseId];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to value categories';
        $result->data = $this->categoryRows;

        return $result;
    }

    public function categoryOutflow($categoryId, $warehouseId, $dateFrom, $dateTo)
    {
        $this->calls[] = ['categoryOutflow', $categoryId, $warehouseId, $dateFrom, $dateTo];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute category outflow';
        $result->data = $this->categoryOutflow;

        return $result;
    }

    // Keyed by 'Y-m' of $pointInTime.
    public function valuationAt($categoryId, $warehouseId, $pointInTime)
    {
        $this->calls[] = ['valuationAt', $categoryId, $warehouseId, $pointInTime];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to reconstruct valuation';
        $result->data = (int) ($this->valuationByMonth[substr((string) $pointInTime, 0, 7)] ?? 0);

        return $result;
    }

    public function monthMovements($categoryId, $warehouseId, $year, $month)
    {
        $this->calls[] = ['monthMovements', $categoryId, $warehouseId, $year, $month];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to total monthly movements';
        $result->data = ['Receipt' => 0, 'Issue' => 0];

        return $result;
    }

    public function warehouseValuation($categoryId, $warehouseId)
    {
        $this->calls[] = ['warehouseValuation', $categoryId, $warehouseId];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to value warehouse';
        $result->data = (int) ($this->warehouseValuations[$warehouseId] ?? 0);

        return $result;
    }

    public function stockValuationLines($filters, $sort, $limit, $offset)
    {
        $this->calls[] = ['stock_valuation', $filters, $sort, $limit, $offset];
        $rows = $this->lines['stock_valuation'];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list stock valuation lines';
        $result->data = ['rows' => array_slice($rows, (int) $offset, (int) $limit), 'total' => count($rows)];

        return $result;
    }

    public function inventoryAgingLines($filters, $sort, $limit, $offset)
    {
        $this->calls[] = ['inventory_aging', $filters, $sort, $limit, $offset];
        $rows = $this->lines['inventory_aging'];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list inventory aging lines';
        $result->data = ['rows' => array_slice($rows, (int) $offset, (int) $limit), 'total' => count($rows)];

        return $result;
    }

    public function slowMovingLines($filters, $sort, $limit, $offset)
    {
        $this->calls[] = ['slow_moving', $filters, $sort, $limit, $offset];
        $rows = $this->lines['slow_moving'];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list slow-moving lines';
        $result->data = ['rows' => array_slice($rows, (int) $offset, (int) $limit), 'total' => count($rows)];

        return $result;
    }

    public function movementLedgerLines($filters, $sort, $limit, $offset)
    {
        $this->calls[] = ['movement_ledger', $filters, $sort, $limit, $offset];
        $rows = $this->lines['movement_ledger'];

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list stock ledger lines';
        $result->data = ['rows' => array_slice($rows, (int) $offset, (int) $limit), 'total' => count($rows)];

        return $result;
    }
}
