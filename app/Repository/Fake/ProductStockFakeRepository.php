<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\ProductStock;
use App\Repository\Interface\ProductStockRepositoryInterface;
use DateTimeImmutable;

// In-memory fake: no real row locking, but mirrors the MySQL repository method shape.
class ProductStockFakeRepository implements ProductStockRepositoryInterface
{
    private $byKey = [];
    private $nextId = 1;

    public function __construct($stocks = [])
    {
        foreach ($stocks as $stock) {
            $this->byKey[$this->key($stock->productId, $stock->warehouseId)] = $stock;
            $this->nextId = max($this->nextId, $stock->id + 1);
        }
    }

    public function find($productId, $warehouseId)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find product stock';
        $result->data = $this->byKey[$this->key($productId, $warehouseId)] ?? null;

        return $result;
    }

    public function lockForUpdate($productId, $warehouseId)
    {
        return $this->find($productId, $warehouseId);
    }

    public function create($productId, $warehouseId, $quantity = 0)
    {
        $id = $this->nextId++;
        $this->byKey[$this->key($productId, $warehouseId)] = new ProductStock(
            $id,
            $productId,
            $warehouseId,
            $quantity,
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create product stock';
        $result->data = $id;

        return $result;
    }

    public function incrementQuantity($productId, $warehouseId, $delta)
    {
        $result = new Result();
        $key = $this->key($productId, $warehouseId);
        $existing = $this->byKey[$key] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byKey[$key] = new ProductStock(
            $existing->id,
            $existing->productId,
            $existing->warehouseId,
            $existing->quantity + $delta,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update product stock quantity';
        $result->data = null;

        return $result;
    }

    public function totalInventoryValue($categoryId = null, $warehouseId = null)
    {
        // Fake has no purchase_price, so the value is always zero.
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute total inventory value';
        $result->data = '0';

        return $result;
    }

    public function sumByProductId($productId)
    {
        $total = 0;
        foreach ($this->byKey as $stock) {
            if ($stock->productId === $productId) {
                $total += $stock->quantity;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to sum product stock';
        $result->data = $total;

        return $result;
    }

    public function findAllWithProduct($productId)
    {
        $rows = [];
        foreach ($this->byKey as $stock) {
            if ($stock->productId === $productId) {
                $rows[] = [
                    'warehouse_id' => $stock->warehouseId,
                    'quantity' => $stock->quantity,
                    'warehouse_code' => 'WH-' . $stock->warehouseId,
                    'warehouse_name' => 'Warehouse ' . $stock->warehouseId,
                    'warehouse_active' => true,
                ];
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list product stock by warehouse';
        $result->data = $rows;

        return $result;
    }

    public function findByWarehouse($warehouseId, $limit = 10, $offset = 0)
    {
        $rows = [];
        foreach ($this->byKey as $stock) {
            if ($stock->warehouseId === $warehouseId) {
                $rows[] = [
                    'product_id' => $stock->productId,
                    'quantity' => $stock->quantity,
                    'updated_at' => null,
                    'sku' => 'SKU-' . $stock->productId,
                    'name' => 'Product ' . $stock->productId,
                    'unit' => 'pcs',
                    'reorder_point' => 10,
                    'is_active' => true,
                    'category_name' => null,
                ];
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list stocks by warehouse';
        $result->data = array_slice($rows, (int) $offset, (int) $limit ?: null);

        return $result;
    }

    public function countByWarehouse($warehouseId)
    {
        $count = 0;
        foreach ($this->byKey as $stock) {
            if ($stock->warehouseId === $warehouseId) {
                $count++;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count stocks by warehouse';
        $result->data = $count;

        return $result;
    }

    public function sumQuantityByWarehouse($warehouseId)
    {
        $total = 0;
        foreach ($this->byKey as $stock) {
            if ($stock->warehouseId === $warehouseId) {
                $total += $stock->quantity;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to sum stock quantity by warehouse';
        $result->data = $total;

        return $result;
    }

    public function findCriticalStock($limit = 10)
    {
        // Fake: returns empty — real data would come from MySQL repository
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list critical stock';
        $result->data = [];

        return $result;
    }

    public function countCriticalStock()
    {
        // Fake: consistent with findCriticalStock() returning no rows
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count critical stock';
        $result->data = 0;

        return $result;
    }

    public function getCategoryValuation()
    {
        // Fake: returns empty — real data would come from MySQL repository
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to get category valuation';
        $result->data = [];

        return $result;
    }

    private function key($productId, $warehouseId)
    {
        return $productId . ':' . $warehouseId;
    }
}
