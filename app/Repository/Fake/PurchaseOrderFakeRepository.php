<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\PurchaseOrder;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;
use DateTimeImmutable;

// In-memory fake backing the unit test suite; no database dependency.
class PurchaseOrderFakeRepository implements PurchaseOrderRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($orders = [])
    {
        foreach ($orders as $order) {
            $this->byId[$order->id] = $order;
            $this->nextId = max($this->nextId, $order->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find purchase order';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findAll($status = null, $limit = 0, $offset = 0, $search = null, $sortDirection = 'desc', $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
        $all = array_values($this->byId);

        // Handle warehouse_ids: array → IN, scalar → equality
        if ($warehouseIds !== null && count($warehouseIds) > 0) {
            $whIds = array_map('intval', $warehouseIds);
            $all = array_values(array_filter($all, function ($po) use ($whIds) {
                return in_array((int) $po->destinationWarehouseId, $whIds, true);
            }));
        }

        // Handle status: array → IN, scalar → equality
        if ($status !== null && count((array) $status) > 0) {
            if (is_array($status)) {
                $all = array_values(array_filter($all, function ($po) use ($status) { return in_array($po->status, $status, true); }));
            } elseif (is_string($status) && $status !== '') {
                $all = array_values(array_filter($all, function ($po) use ($status) { return $po->status === $status; }));
            }
        }

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($po) use ($search) {
                return stripos((string) $po->id, $search) !== false
                    || stripos((string) ($po->supplierName ?? ''), $search) !== false;
            }));
        }

        if ($orderNumber !== null && $orderNumber !== '') {
            $all = array_values(array_filter($all, function ($po) use ($orderNumber) {
                return stripos((string) $po->id, $orderNumber) !== false;
            }));
        }

        if ($supplierName !== null && $supplierName !== '') {
            $all = array_values(array_filter($all, function ($po) use ($supplierName) {
                return stripos((string) ($po->supplierName ?? ''), $supplierName) !== false;
            }));
        }

        $direction = strtoupper((string) $sortDirection) === 'ASC' ? 1 : -1;
        usort($all, function ($a, $b) use ($direction) { return $direction * ($a->id <=> $b->id); });

        if ($limit > 0 || $offset > 0) {
            $all = array_slice($all, $offset, $limit > 0 ? $limit : null);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list purchase orders';
        $result->data = $all;

        return $result;
    }

    public function countAll($status = null, $search = null, $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
        $all = array_values($this->byId);

        if ($warehouseIds !== null && count($warehouseIds) > 0) {
            $whIds = array_map('intval', $warehouseIds);
            $all = array_filter($all, function ($po) use ($whIds) {
                return in_array((int) $po->destinationWarehouseId, $whIds, true);
            });
        }
        if ($status !== null && count((array) $status) > 0) {
            if (is_array($status)) {
                $all = array_filter($all, function ($po) use ($status) { return in_array($po->status, $status, true); });
            } elseif (is_string($status) && $status !== '') {
                $all = array_filter($all, function ($po) use ($status) { return $po->status === $status; });
            }
        }

        if ($search !== null && $search !== '') {
            $all = array_filter($all, function ($po) use ($search) {
                return stripos((string) $po->id, $search) !== false
                    || stripos((string) ($po->supplierName ?? ''), $search) !== false;
            });
        }

        if ($orderNumber !== null && $orderNumber !== '') {
            $all = array_filter($all, function ($po) use ($orderNumber) {
                return stripos((string) $po->id, $orderNumber) !== false;
            });
        }

        if ($supplierName !== null && $supplierName !== '') {
            $all = array_filter($all, function ($po) use ($supplierName) {
                return stripos((string) ($po->supplierName ?? ''), $supplierName) !== false;
            });
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count purchase orders';
        $result->data = count($all);

        return $result;
    }

    public function countByStatus()
    {
        $counts = [];
        foreach ($this->byId as $po) {
            $counts[$po->status] = ($counts[$po->status] ?? 0) + 1;
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count purchase orders by status';
        $result->data = $counts;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new PurchaseOrder(
            $id,
            (int) $data['supplier_id'],
            (int) $data['destination_warehouse_id'],
            (string) $data['status'],
            (string) $data['order_date'],
            $data['note'] ?? null,
            (int) $data['created_by'],
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create purchase order';
        $result->data = $id;

        return $result;
    }

    // Fake: no real row lock, just the current id/status.
    public function lockForUpdate($id)
    {
        $po = $this->byId[$id] ?? null;

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to lock purchase order';
        $result->data = $po !== null ? ['id' => $po->id, 'status' => $po->status] : null;

        return $result;
    }

    public function updateStatus($id, $status)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new PurchaseOrder(
            $existing->id,
            $existing->supplierId,
            $existing->destinationWarehouseId,
            $status,
            $existing->orderDate,
            $existing->note,
            $existing->createdBy,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update purchase order status';
        $result->data = null;

        return $result;
    }

    public function findForExport($from, $to, $warehouseId = null)
    {
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs = strtotime($to . ' 23:59:59');

        $rows = [];
        foreach ($this->byId as $po) {
            if ($warehouseId !== null && (int) $po->destinationWarehouseId !== (int) $warehouseId) {
                continue;
            }
            $ts = strtotime($po->orderDate);
            if ($ts >= $fromTs && $ts <= $toTs) {
                $rows[] = [
                    'order_type'  => 'PO',
                    'id'          => $po->id,
                    'order_date'  => $po->orderDate,
                    'status'      => $po->status,
                    'party_name'  => '',
                    'warehouse_name' => '',
                    'creator'     => '',
                    'items_count' => 0,
                    'total_value' => '',
                ];
            }
        }

        usort($rows, function ($a, $b) { return strcmp($b['order_date'], $a['order_date']); });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to export purchase orders';
        $result->data = $rows;

        return $result;
    }
}
