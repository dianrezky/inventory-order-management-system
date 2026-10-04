<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\ProductStockRepositoryInterface;

// JOB-01 ("Scheduled Job — Notifikasi stok rendah"), extracted out of
// scripts/check-low-stock.php so the rule (total stock across all warehouses
// below reorder point) goes through the same Repository layer as everything
// else and is unit-testable without a database.
class LowStockService
{
    private $productRepository;
    private $productStockRepository;
    private $notificationService;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductStockRepositoryInterface $productStockRepository,
        NotificationService $notificationService
    ) {
        $this->productRepository = $productRepository;
        $this->productStockRepository = $productStockRepository;
        $this->notificationService = $notificationService;
    }

    // Finds every active product whose total quantity (summed across all
    // warehouses) is below its reorder point, creates a deduplicated
    // low-stock notification for each (NotificationService already skips
    // products that still have an unread one), and returns the rows plus how
    // many notifications were newly created — for the CLI report output.
    public function checkAndNotify()
    {
        $result = new Result();

        $lowStockResult = $this->productRepository->findLowStock();
        if ($lowStockResult->code !== Result::CODE_SUCCESS) {
            return $lowStockResult;
        }

        $rows = [];
        $notifiedCount = 0;

        foreach ($lowStockResult->data as $product) {
            $quantityResult = $this->productStockRepository->sumByProductId($product->id);
            $quantity = $quantityResult->code === Result::CODE_SUCCESS ? $quantityResult->data : 0;

            $message = sprintf(
                '%s is below reorder point (%s in stock across all warehouses, reorder at %s).',
                $product->name,
                number_format($quantity),
                number_format($product->reorderPoint)
            );

            $notifyResult = $this->notificationService->notifyLowStock($product->id, null, $message);
            if ($notifyResult->code === Result::CODE_SUCCESS && $notifyResult->data !== null) {
                $notifiedCount++;
            }

            $rows[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $quantity,
                'reorder_point' => $product->reorderPoint,
            ];
        }

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check low stock products';
        $result->data = [
            'rows' => $rows,
            'notified_count' => $notifiedCount,
        ];

        return $result;
    }
}
