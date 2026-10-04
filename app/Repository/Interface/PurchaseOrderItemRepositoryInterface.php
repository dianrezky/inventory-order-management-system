<?php

namespace App\Repository\Interface;

use App\Entity\PurchaseOrderItem;

interface PurchaseOrderItemRepositoryInterface
{
    public function findById($id);

    // Same as findById() but SELECT … FOR UPDATE (qty columns only), so the
    // received quantity read here is current and can't change until commit.
    public function findByIdForUpdate($id);

    public function findByPurchaseOrderId($purchaseOrderId);

    public function create($data);

    public function incrementQtyReceived($id, $qty);
}
