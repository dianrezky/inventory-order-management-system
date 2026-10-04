<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\PurchaseOrderItem;
use App\Repository\Interface\PurchaseOrderItemRepositoryInterface;

class PurchaseOrderItemMySQLRepository implements PurchaseOrderItemRepositoryInterface
{
    private $queryBuilder;

    private const COLUMNS = 'poi.*, p.name AS product_name, p.sku AS product_sku, p.unit AS product_unit';

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    private function productJoin(): array
    {
        return [
            ['type' => 'LEFT', 'table' => 'products', 'alias' => 'p', 'on' => 'p.id = poi.product_id'],
        ];
    }

    public function findById($id)
    {
        $result = new Result();

        try {
            $row = $this->queryBuilder->findOne(
                'purchase_order_items',
                'poi',
                self::COLUMNS,
                $this->productJoin(),
                ['poi.id' => $id]
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to find purchase order item';
            $result->data = $row === null ? null : PurchaseOrderItem::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findByIdForUpdate($id)
    {
        $result = new Result();

        try {
            // No product join: FOR UPDATE would otherwise lock the joined products row too.
            $row = $this->queryBuilder->findOne(
                'purchase_order_items',
                'poi',
                'poi.*',
                [],
                ['poi.id' => $id],
                [],
                true
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to lock purchase order item';
            $result->data = $row === null ? null : PurchaseOrderItem::fromArray($row);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findByPurchaseOrderId($purchaseOrderId)
    {
        $result = new Result();

        try {
            $rows = $this->queryBuilder->findAll(
                'purchase_order_items',
                'poi',
                self::COLUMNS,
                $this->productJoin(),
                [],
                null,
                ['poi.purchase_order_id' => $purchaseOrderId],
                'poi.id ASC'
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list purchase order items';
            $result->data = array_map(function ($row) { return PurchaseOrderItem::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function create($data)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('purchase_order_items', [
                'purchase_order_id' => (int) $data['purchase_order_id'],
                'product_id' => (int) $data['product_id'],
                'qty_ordered' => (int) $data['qty_ordered'],
                'qty_received' => 0,
                'purchase_price' => (string) $data['purchase_price'],
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create purchase order item';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function incrementQtyReceived($id, $qty)
    {
        $result = new Result();

        try {
            $this->queryBuilder->incrementColumn('purchase_order_items', 'qty_received', $qty, ['id' => $id]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to update purchase order item received quantity';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
