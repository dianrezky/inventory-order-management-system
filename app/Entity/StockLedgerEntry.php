<?php

namespace App\Entity;

use DateTimeImmutable;

// Insert-only ledger: rows are never updated or deleted after creation (BR-015).
class StockLedgerEntry
{
    public const TYPE_RECEIPT = 'Receipt';
    public const TYPE_ISSUE = 'Issue';
    public const TYPE_ADJUSTMENT = 'Adjustment';

    public const REF_PO = 'PO';
    public const REF_SO = 'SO';
    public const REF_ADJUSTMENT = 'Adjustment';

    public $id;
    public $productId;
    public $warehouseId;
    public $type;
    public $qty;
    public $refType;
    public $refId;
    public $note;
    public $doneByUserId;
    public $doneAt;

    // Populated only when the row was fetched via a join (e.g. findByReference()'s
    // product/warehouse/user join). Plain inserts (StockLedgerFakeRepository::insert(),
    // and any other call site that doesn't pass these) leave them null — callers must
    // treat null as "name not loaded", not as missing data, and fall back accordingly.
    public $productName;
    public $productSku;
    public $warehouseName;
    public $userName;

    public function __construct(
        $id,
        $productId,
        $warehouseId,
        $type,
        $qty,
        $refType,
        $refId,
        $note,
        $doneByUserId,
        $doneAt = null,
        $productName = null,
        $productSku = null,
        $warehouseName = null,
        $userName = null
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->warehouseId = $warehouseId;
        $this->type = $type;
        $this->qty = $qty;
        $this->refType = $refType;
        $this->refId = $refId;
        $this->note = $note;
        $this->doneByUserId = $doneByUserId;
        $this->doneAt = $doneAt;
        $this->productName = $productName;
        $this->productSku = $productSku;
        $this->warehouseName = $warehouseName;
        $this->userName = $userName;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['product_id'],
            (int) $row['warehouse_id'],
            (string) $row['type'],
            (int) $row['qty'],
            isset($row['ref_type']) && $row['ref_type'] !== null ? (string) $row['ref_type'] : null,
            isset($row['ref_id']) && $row['ref_id'] !== null ? (int) $row['ref_id'] : null,
            isset($row['note']) && $row['note'] !== null ? (string) $row['note'] : null,
            (int) $row['done_by_user_id'],
            isset($row['done_at']) ? new DateTimeImmutable((string) $row['done_at']) : null,
            isset($row['product_name']) && $row['product_name'] !== null ? (string) $row['product_name'] : null,
            isset($row['sku']) && $row['sku'] !== null ? (string) $row['sku'] : null,
            isset($row['warehouse_name']) && $row['warehouse_name'] !== null ? (string) $row['warehouse_name'] : null,
            isset($row['user_name']) && $row['user_name'] !== null ? (string) $row['user_name'] : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'type' => $this->type,
            'qty' => $this->qty,
            'ref_type' => $this->refType,
            'ref_id' => $this->refId,
            'note' => $this->note,
            'done_by_user_id' => $this->doneByUserId,
            'done_at' => $this->doneAt ? $this->doneAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
