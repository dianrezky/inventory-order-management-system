<?php

namespace App\Entity;

use DateTimeImmutable;

class SalesOrder
{
    // Status lifecycle: Draft -> PendingApproval -> Approved -> Fulfilled | Cancelled
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_APPROVAL = 'PendingApproval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_FULFILLED = 'Fulfilled';
    public const STATUS_CANCELLED = 'Cancelled';

    public $id;
    public $customerId;
    public $sourceWarehouseId;
    public $status;
    public $orderDate;
    public $note;
    public $createdBy;
    public $approvedBy;
    public $approvedAt;
    public $issuedBy;
    public $issuedAt;
    public $cancellationReason;
    public $createdAt;
    public $updatedAt;
    // Joined fields, populated by list/detail queries only
    public $customerName;
    public $sourceWarehouseName;
    public $createdByName;
    public $approvedByName;
    public $issuedByName;
    public $items;
    // List-view aggregates (populated by findAll() when joined with sales_order_items)
    public $itemsCount = 0;
    public $itemsQty = 0;
    public $totalValue = '0';

    public function __construct(
        $id,
        $customerId,
        $sourceWarehouseId,
        $status,
        $orderDate,
        $note,
        $createdBy,
        $approvedBy = null,
        $approvedAt = null,
        $issuedBy = null,
        $issuedAt = null,
        $cancellationReason = null,
        $createdAt = null,
        $updatedAt = null,
        $customerName = null,
        $sourceWarehouseName = null,
        $createdByName = null,
        $approvedByName = null,
        $issuedByName = null,
        $items = []
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->sourceWarehouseId = $sourceWarehouseId;
        $this->status = $status;
        $this->orderDate = $orderDate;
        $this->note = $note;
        $this->createdBy = $createdBy;
        $this->approvedBy = $approvedBy;
        $this->approvedAt = $approvedAt;
        $this->issuedBy = $issuedBy;
        $this->issuedAt = $issuedAt;
        $this->cancellationReason = $cancellationReason;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->customerName = $customerName;
        $this->sourceWarehouseName = $sourceWarehouseName;
        $this->createdByName = $createdByName;
        $this->approvedByName = $approvedByName;
        $this->issuedByName = $issuedByName;
        $this->items = $items;
    }

    public static function fromArray($row)
    {
        $so = new self(
            (int) $row['id'],
            (int) $row['customer_id'],
            (int) $row['source_warehouse_id'],
            (string) $row['status'],
            (string) $row['order_date'],
            $row['note'] !== null ? (string) $row['note'] : null,
            (int) $row['created_by'],
            isset($row['approved_by']) && $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            isset($row['approved_at']) && $row['approved_at'] !== null ? new DateTimeImmutable((string) $row['approved_at']) : null,
            isset($row['issued_by']) && $row['issued_by'] !== null ? (int) $row['issued_by'] : null,
            isset($row['issued_at']) && $row['issued_at'] !== null ? new DateTimeImmutable((string) $row['issued_at']) : null,
            $row['cancellation_reason'] !== null ? (string) $row['cancellation_reason'] : null,
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
            $row['customer_name'] ?? null,
            $row['source_warehouse_name'] ?? null,
            $row['created_by_name'] ?? null,
            $row['approved_by_name'] ?? null,
            $row['issued_by_name'] ?? null
        );

        // Aggregates populated when list query joins sales_order_items (GROUP BY so.id)
        $so->itemsCount = isset($row['items_count']) ? (int) $row['items_count'] : 0;
        $so->itemsQty   = isset($row['items_qty'])   ? (int) $row['items_qty']   : 0;
        $so->totalValue = isset($row['total_value']) ? (string) $row['total_value'] : '0';

        return $so;
    }

    public function withItems($items)
    {
        return new self(
            $this->id,
            $this->customerId,
            $this->sourceWarehouseId,
            $this->status,
            $this->orderDate,
            $this->note,
            $this->createdBy,
            $this->approvedBy,
            $this->approvedAt,
            $this->issuedBy,
            $this->issuedAt,
            $this->cancellationReason,
            $this->createdAt,
            $this->updatedAt,
            $this->customerName,
            $this->sourceWarehouseName,
            $this->createdByName,
            $this->approvedByName,
            $this->issuedByName,
            $items
        );
    }

    public function canIssueGoods()
    {
        // BR-013: goods issue is allowed only while the order is Approved
        return $this->status === self::STATUS_APPROVED;
    }

    // Display name for the UI. The label belongs to the order itself, so views
    // never have to look it up in a message table.
    public function statusLabel()
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor($status)
    {
        if ($status === self::STATUS_PENDING_APPROVAL) {
            return 'Pending Approval';
        }

        return (string) $status;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'source_warehouse_id' => $this->sourceWarehouseId,
            'status' => $this->status,
            'order_date' => $this->orderDate,
            'note' => $this->note,
            'created_by' => $this->createdBy,
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approvedAt ? $this->approvedAt->format('Y-m-d H:i:s') : null,
            'issued_by' => $this->issuedBy,
            'issued_at' => $this->issuedAt ? $this->issuedAt->format('Y-m-d H:i:s') : null,
            'cancellation_reason' => $this->cancellationReason,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
