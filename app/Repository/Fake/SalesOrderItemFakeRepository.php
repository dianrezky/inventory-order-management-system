<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;

// In-memory fake backing the unit test suite; no database dependency.
class SalesOrderItemFakeRepository implements SalesOrderItemRepositoryInterface
{
    private $items = [];

    public function replaceForSalesOrder($salesOrderId, $items)
    {
        $result = new Result();
        try {
            $retained = array_values(array_filter($this->items, static function ($item) use ($salesOrderId) {
                return $item->salesOrderId !== $salesOrderId;
            }));
            $nextId = 1;
            foreach ($this->items as $existing) {
                $nextId = max($nextId, $existing->id + 1);
            }
            foreach ($items as $item) {
                $retained[] = new \App\Entity\SalesOrderItem($nextId++, $salesOrderId, $item['product_id'], $item['qty'], $item['sale_price']);
            }
            $this->items = $retained;
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Draft sales order items replaced.';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }
        return $result;
    }

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
