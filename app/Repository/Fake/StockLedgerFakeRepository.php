<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\StockLedgerEntry;
use App\Repository\Interface\StockLedgerRepositoryInterface;
use DateTimeImmutable;

// In-memory fake backing the unit test suite; no database dependency.
class StockLedgerFakeRepository implements StockLedgerRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($entries = [])
    {
        foreach ($entries as $entry) {
            $this->byId[$entry->id] = $entry;
            $this->nextId = max($this->nextId, $entry->id + 1);
        }
    }

    public function insert($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new StockLedgerEntry(
            $id,
            (int) $data['product_id'],
            (int) $data['warehouse_id'],
            (string) $data['type'],
            (int) $data['qty'],
            isset($data['ref_type']) && $data['ref_type'] !== null ? (string) $data['ref_type'] : null,
            isset($data['ref_id']) && $data['ref_id'] !== null ? (int) $data['ref_id'] : null,
            $data['note'] ?? null,
            (int) $data['done_by_user_id'],
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to insert stock ledger entry';
        $result->data = $id;

        return $result;
    }

    public function listByProductWarehouse($productId, $warehouseId)
    {
        $entries = array_values(array_filter(
            $this->byId,
            function ($e) use ($productId, $warehouseId) {
                return $e->productId === $productId && $e->warehouseId === $warehouseId;
            }
        ));

        usort($entries, function ($a, $b) { return $a->id <=> $b->id; });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list stock ledger entries';
        $result->data = $entries;

        return $result;
    }

    public function sumByProductWarehouse($productId, $warehouseId)
    {
        $listResult = $this->listByProductWarehouse($productId, $warehouseId);

        $sum = 0;
        foreach ($listResult->data as $entry) {
            $sum += $entry->qty;
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to sum stock ledger entries';
        $result->data = $sum;

        return $result;
    }

    public function findFiltered($filters, $limit, $offset)
    {
        $filtered = array_values($this->byId);

        usort($filtered, function ($a, $b) use ($filters) {
            $col = $filters['sort_col'] ?? 'done_at';
            $dir = ($filters['sort_dir'] ?? 'DESC') === 'ASC' ? 1 : -1;
            return $this->compareLedgerEntry($a, $b, $col) * $dir;
        });

        $total = count($filtered);
        $sliced = array_slice($filtered, $offset, $limit);

        $rows = array_map(function ($e) {
            return [
                'id' => $e->id,
                'product_id' => $e->productId,
                'warehouse_id' => $e->warehouseId,
                'type' => $e->type,
                'qty' => $e->qty,
                'ref_type' => $e->refType,
                'ref_id' => $e->refId,
                'note' => $e->note,
                'done_by_user_id' => $e->doneByUserId,
                'done_at' => $e->doneAt->format('Y-m-d H:i:s'),
            ];
        }, $sliced);

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to filter stock ledger entries';
        $result->data = [$rows, $total];

        return $result;
    }

    // $categoryId / $search are accepted for interface parity but not applied:
    // this fake stores ledger entries without product category/name data.
    public function findForExport($from, $to, $warehouseId = null, $categoryId = null, $search = null)
    {
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs = strtotime($to . ' 23:59:59');

        $rows = [];
        foreach ($this->byId as $e) {
            $ts = $e->doneAt->getTimestamp();
            if ($warehouseId !== null && $e->warehouseId !== (int) $warehouseId) {
                continue;
            }
            if ($ts >= $fromTs && $ts <= $toTs) {
                $rows[] = [
                    'product_id'    => $e->productId,
                    'product_name'  => '',
                    'product_sku'   => '',
                    'warehouse_name' => '',
                    'type'          => $e->type,
                    'ref_type'      => $e->refType,
                    'ref_id'        => $e->refId,
                    'user_name'     => '',
                    'done_at'       => $e->doneAt->format('Y-m-d H:i:s'),
                    'qty'           => $e->qty,
                ];
            }
        }

        usort($rows, function ($a, $b) { return strcmp($b['done_at'], $a['done_at']); });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to export stock ledger entries';
        $result->data = array_slice($rows, 0, 5000);

        return $result;
    }

    public function findByReference($refType, $refId, $limit = 50)
    {
        $entries = array_values(array_filter(
            $this->byId,
            function ($e) use ($refType, $refId) {
                return $e->refType === $refType && (int) $e->refId === (int) $refId;
            }
        ));

        usort($entries, function ($a, $b) {
            return $b->doneAt <=> $a->doneAt;
        });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list ledger entries by reference';
        $result->data = array_slice($entries, 0, $limit);

        return $result;
    }

    private function compareLedgerEntry($a, $b, $col)
    {
        if ($col === 'type') {
            return $a->type <=> $b->type;
        }

        if ($col === 'qty') {
            return $a->qty <=> $b->qty;
        }

        return $a->doneAt <=> $b->doneAt;
    }
}
