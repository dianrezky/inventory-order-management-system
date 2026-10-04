<?php

namespace App\Repository\Interface;

use App\Entity\Product;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface ProductRepositoryInterface
{
    public function findById($id);

    public function findBySku($sku);

    public function findAll($search = null, $limit = 0, $offset = 0, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null);

    public function countAll($search = null, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null);

    public function findLowStock();

    public function findAllActive();

    public function skuExists($sku, $excludeId = null);

    public function create($data);

    public function update($id, $data);

    public function updateImagePath($id, $imagePath);

    public function setActive($id, $active);

    /** Returns aggregate counts keyed by stock status: total, normal, low, out, inactive. */
    public function countByStockStatus();
}
