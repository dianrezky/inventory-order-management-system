<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\SalesOrder;
use App\Repository\Interface\SalesOrderRepositoryInterface;

// In-memory fake backing the unit test suite; no database dependency.
class SalesOrderFakeRepository implements SalesOrderRepositoryInterface
{
    private $orders = [];
    private $nextId = 1;

    public function findById($id)
    {
        $found = null;
        foreach ($this->orders as $o) {
            if ($o->id === $id) {
                $found = $o;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find sales order';
        $result->data = $found;

        return $result;
    }

    public function findAll($userId = null, $filters = [], $limit = 0, $offset = 0)
    {
        $rows = $this->orders;

        if ($userId !== null) {
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($userId) { return $o->createdBy === $userId; }
            ));
        }

        // Handle status: array → IN, scalar → equality
        $statusRaw = $filters['status'] ?? null;
        if (is_array($statusRaw) && count($statusRaw) > 0) {
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($statusRaw) { return in_array($o->status, $statusRaw, true); }
            ));
        } elseif (is_string($statusRaw) && $statusRaw !== '') {
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($statusRaw) { return $o->status === $statusRaw; }
            ));
        }

        // Handle warehouse_id: array → IN, scalar → equality
        $warehouseRaw = $filters['warehouse_id'] ?? null;
        if (is_array($warehouseRaw) && count($warehouseRaw) > 0) {
            $warehouseIds = array_map('intval', $warehouseRaw);
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($warehouseIds) { return in_array((int) $o->sourceWarehouseId, $warehouseIds, true); }
            ));
        } elseif (is_string($warehouseRaw) && $warehouseRaw !== '') {
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($warehouseRaw) { return (int) $o->sourceWarehouseId === (int) $warehouseRaw; }
            ));
        }

        if (($filters['search'] ?? '') !== '') {
            $search = (string) $filters['search'];
            $rows = array_values(array_filter(
                $rows,
                function ($o) use ($search) {
                    return stripos((string) $o->id, $search) !== false
                        || stripos((string) ($o->customerName ?? ''), $search) !== false;
                }
            ));
        }

        $direction = strtoupper((string) ($filters['sort'] ?? 'desc')) === 'ASC' ? 1 : -1;
        usort($rows, function ($a, $b) use ($direction) { return $direction * ($a->id <=> $b->id); });

        if ($limit > 0 || $offset > 0) {
            $rows = array_slice($rows, $offset, $limit > 0 ? $limit : null);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list sales orders';
        $result->data = $rows;

        return $result;
    }

    public function countAll($userId = null, $filters = [])
    {
        $findResult = $this->findAll($userId, $filters);

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count sales orders';
        $result->data = count($findResult->data);

        return $result;
    }

    public function countByStatus($userId = null, $dateFrom = null, $dateTo = null)
    {
        $orders = $this->orders;
        if ($userId !== null) {
            $orders = array_filter($orders, function ($o) use ($userId) { return $o->createdBy === $userId; });
        }
        if ($dateFrom !== null) {
            $orders = array_filter($orders, function ($o) use ($dateFrom) { return (string) $o->orderDate >= $dateFrom; });
        }
        if ($dateTo !== null) {
            $orders = array_filter($orders, function ($o) use ($dateTo) { return (string) $o->orderDate <= $dateTo; });
        }

        $counts = [];
        foreach ($orders as $o) {
            $counts[$o->status] = ($counts[$o->status] ?? 0) + 1;
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count sales orders by status';
        $result->data = $counts;

        return $result;
    }

    public function create($header, $items)
    {
        $id = $this->nextId++;
        $this->orders[] = new SalesOrder(
            $id,
            (int) $header['customer_id'],
            (int) $header['source_warehouse_id'],
            (string) $header['status'],
            (string) $header['order_date'],
            $header['note'] ?? null,
            (int) $header['created_by']
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create sales order';
        $result->data = $id;

        return $result;
    }

    public function updateStatus($id, $status, $extras = [])
    {
        $found = false;

        foreach ($this->orders as &$o) {
            if ($o->id === $id) {
                $o = new SalesOrder(
                    $o->id,
                    $o->customerId,
                    $o->sourceWarehouseId,
                    $status,
                    $o->orderDate,
                    $o->note,
                    $o->createdBy,
                    isset($extras['approved_by']) ? $extras['approved_by'] : $o->approvedBy,
                    $o->approvedAt,
                    isset($extras['issued_by']) ? $extras['issued_by'] : $o->issuedBy,
                    $o->issuedAt,
                    isset($extras['cancellation_reason']) ? $extras['cancellation_reason'] : $o->cancellationReason,
                    $o->createdAt,
                    $o->updatedAt
                );
                $found = true;
                break;
            }
        }
        unset($o);

        $result = new Result();

        if (!$found) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update sales order status';
        $result->data = null;

        return $result;
    }

    public function findForExport($from, $to, $userId = null, $warehouseId = null)
    {
        $fromTs = strtotime($from . ' 00:00:00');
        $toTs = strtotime($to . ' 23:59:59');

        $rows = [];
        foreach ($this->orders as $so) {
            if ($userId !== null && $so->createdBy !== $userId) {
                continue;
            }
            if ($warehouseId !== null && (int) $so->sourceWarehouseId !== (int) $warehouseId) {
                continue;
            }

            $ts = strtotime($so->orderDate);
            if ($ts >= $fromTs && $ts <= $toTs) {
                $rows[] = [
                    'order_type'  => 'SO',
                    'id'          => $so->id,
                    'order_date'  => $so->orderDate,
                    'status'      => $so->status,
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
        $result->info = 'Success to export sales orders';
        $result->data = $rows;

        return $result;
    }

    public function lockForUpdate($id)
    {
        // Fake only checks existence; no real row lock is taken.
        $found = null;
        foreach ($this->orders as $o) {
            if ($o->id === $id) {
                $found = $o;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to lock sales order';
        $result->data = $found !== null ? [['id' => $id, 'status' => $found->status]] : [];

        return $result;
    }

    public function totalRevenue($userId = null, $dateFrom = null, $dateTo = null)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute total revenue';
        $result->data = 0.0;

        return $result;
    }

    public function getTopCustomers($userId = null, $limit = 5, $dateFrom = null, $dateTo = null)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list top customers';
        $result->data = [];

        return $result;
    }
}
