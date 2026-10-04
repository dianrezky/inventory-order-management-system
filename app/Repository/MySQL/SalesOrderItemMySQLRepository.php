<?php

namespace App\Repository\MySQL;

use App\Core\Result;
use App\Entity\SalesOrderItem;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;

class SalesOrderItemMySQLRepository implements SalesOrderItemRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function findById($id)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll('sales_order_items', null, '*', [], [], null, ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find sales order items';
            $result->data = array_map(function ($row) { return SalesOrderItem::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findBySalesOrderId($salesOrderId)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'sales_order_items',
                null,
                '*',
                [],
                [],
                null,
                ['sales_order_id' => $salesOrderId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list sales order items';
            $result->data = array_map(function ($row) { return SalesOrderItem::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findBySalesOrderIdWithProduct($salesOrderId)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'sales_order_items',
                'soi',
                'soi.*, p.name AS product_name, p.sku AS product_sku, p.unit AS product_unit',
                [['type' => 'INNER', 'table' => 'products', 'alias' => 'p', 'on' => 'p.id = soi.product_id']],
                [],
                null,
                ['soi.sales_order_id' => $salesOrderId]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list sales order items with product';
            $result->data = array_map(function ($row) { return SalesOrderItem::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
