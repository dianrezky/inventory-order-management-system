<?php

namespace App\Repository\Interface;

use App\Entity\SalesOrderItem;

interface SalesOrderItemRepositoryInterface
{
    public function findBySalesOrderId($salesOrderId);

    public function findBySalesOrderIdWithProduct($salesOrderId);
}
