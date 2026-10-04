<?php

namespace App\Repository\Interface;

use App\Entity\StockLedgerEntry;

// Insert-only by design (db-schema-design.md §2.12) — no update() or delete() exists.
interface StockLedgerRepositoryInterface
{
    public function insert($data);

    public function listByProductWarehouse($productId, $warehouseId);

    public function sumByProductWarehouse($productId, $warehouseId);

    public function findFiltered($filters, $limit, $offset);

    public function findForExport($from, $to, $warehouseId = null, $categoryId = null, $search = null);

    // Returns the audit-trail entries linked to a specific order reference
    // (ref_type='PO' + ref_id=<po_id> OR ref_type='SO' + ref_id=<so_id>),
    // ordered DESC by done_at. Used by the PO/SO detail "Audit log" timelines.
    public function findByReference($refType, $refId, $limit = 50);
}
