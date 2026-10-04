<?php

namespace App\Repository\Interface;

use App\Entity\PurchaseOrder;

// ARCH-01: the Container memoizes one PDO connection per request, so calls made inside Database::transaction() join that transaction automatically.
interface PurchaseOrderRepositoryInterface
{
    public function findById($id);

    public function findAll($status = null, $limit = 0, $offset = 0, $search = null, $sortDirection = 'desc', $warehouseIds = null, $orderNumber = null, $supplierName = null);

    public function countAll($status = null, $search = null, $warehouseIds = null, $orderNumber = null, $supplierName = null);

    public function countByStatus();

    public function findForExport($from, $to, $warehouseId = null);

    // SELECT … FOR UPDATE on the PO row, inside the caller's transaction.
    // data: ['id' => int, 'status' => string] read under the lock, or null.
    public function lockForUpdate($id);

    public function create($data);

    public function updateStatus($id, $status, $expectedStatus = null);
}
