<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\ProductStockRepositoryInterface;

class ProductStockService
{
    private $productStockRepository;

    public function __construct(ProductStockRepositoryInterface $productStockRepository)
    {
        $this->productStockRepository = $productStockRepository;
    }

    public function listByWarehouse($warehouseId, $limit = 10, $offset = 0)
    {
        $findResult = $this->productStockRepository->findByWarehouse($warehouseId, $limit, $offset);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countByWarehouse($warehouseId)
    {
        $countResult = $this->productStockRepository->countByWarehouse($warehouseId);

        return $countResult->code === Result::CODE_SUCCESS ? (int) $countResult->data : 0;
    }

    public function totalQuantityForWarehouse($warehouseId)
    {
        $sumResult = $this->productStockRepository->sumQuantityByWarehouse($warehouseId);

        return $sumResult->code === Result::CODE_SUCCESS ? (int) $sumResult->data : 0;
    }
}
