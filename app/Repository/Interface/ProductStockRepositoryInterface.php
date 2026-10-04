<?php

namespace App\Repository\Interface;

use App\Entity\ProductStock;

// ADR-002: lockForUpdate() must only be called inside an existing Database::transaction() callback.
interface ProductStockRepositoryInterface
{
    public function find($productId, $warehouseId);

    public function lockForUpdate($productId, $warehouseId);

    public function create($productId, $warehouseId, $quantity = 0);

    public function incrementQuantity($productId, $warehouseId, $delta);

    public function totalInventoryValue($categoryId = null, $warehouseId = null);

    public function sumByProductId($productId);

    public function findAllWithProduct($productId);

    // Returns a paginated list of products stocked at the given warehouse,
    // joined with product master data. Used by the warehouse detail page.
    public function findByWarehouse($warehouseId, $limit = 10, $offset = 0);

    public function countByWarehouse($warehouseId);

    /** Returns the sum of on-hand quantity across all products stocked at the given warehouse. */
    public function sumQuantityByWarehouse($warehouseId);

    /** Returns stock entries below reorder point with warehouse + product info for dashboard alerts. */
    public function findCriticalStock($limit = 10);

    // Uncapped number of (active product, active warehouse) stock rows below reorder point, for the dashboard KPI
    public function countCriticalStock();

    /** Returns per-category inventory valuation aggregated by product_stocks JOIN products JOIN categories. */
    public function getCategoryValuation();
}
