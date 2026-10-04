<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;

// In-memory fake backing the unit test suite; no database dependency.
class SalesOrderItemFakeRepository implements SalesOrderItemRepositoryInterface
{
    private $items = [];

    public function findBySalesOrderId($salesOrderId)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list sales order items';
        $result->data = array_values(array_filter(
            $this->items,
            function ($i) use ($salesOrderId) { return $i->salesOrderId === $salesOrderId; }
        ));

        return $result;
    }

    public function findBySalesOrderIdWithProduct($salesOrderId)
    {
        return $this->findBySalesOrderId($salesOrderId);
    }
}
