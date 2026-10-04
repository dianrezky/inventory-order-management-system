<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Result;
use App\Entity\Product;
use App\Entity\ProductStock;
use App\Repository\Fake\NotificationFakeRepository;
use App\Repository\Fake\ProductFakeRepository;
use App\Repository\Fake\ProductStockFakeRepository;
use App\Service\LowStockService;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

final class LowStockServiceTest extends TestCase
{
    private function makeProduct(int $id, string $sku, string $name, int $reorderPoint): Product
    {
        return new Product(
            $id,
            $sku,
            $name,
            1,
            'pcs',
            '1000.00',
            '1500.00',
            $reorderPoint,
            null,
            true
        );
    }

    private function makeService(ProductFakeRepository $products, ProductStockFakeRepository $stocks): LowStockService
    {
        return new LowStockService(
            $products,
            $stocks,
            new NotificationService(new NotificationFakeRepository())
        );
    }

    public function testFlagsProductBelowReorderPointAndCreatesNotification(): void
    {
        $product = $this->makeProduct(1, 'SKU-1', 'Widget', 10);
        $products = new ProductFakeRepository([$product]);
        $stocks = new ProductStockFakeRepository([
            new ProductStock(1, 1, 1, 3),
        ]);

        $result = $this->makeService($products, $stocks)->checkAndNotify();

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertCount(1, $result->data['rows']);
        self::assertSame(3, $result->data['rows'][0]['quantity']);
        self::assertSame(10, $result->data['rows'][0]['reorder_point']);
        self::assertSame('SKU-1', $result->data['rows'][0]['sku']);
        self::assertSame(1, $result->data['notified_count']);
    }

    public function testSumsQuantityAcrossAllWarehouses(): void
    {
        $product = $this->makeProduct(1, 'SKU-1', 'Widget', 10);
        $products = new ProductFakeRepository([$product]);
        $stocks = new ProductStockFakeRepository([
            new ProductStock(1, 1, 1, 3),
            new ProductStock(2, 1, 2, 2),
        ]);

        $result = $this->makeService($products, $stocks)->checkAndNotify();

        self::assertSame(5, $result->data['rows'][0]['quantity']);
    }

    public function testDoesNotDuplicateNotificationOnSecondRun(): void
    {
        $product = $this->makeProduct(1, 'SKU-1', 'Widget', 10);
        $products = new ProductFakeRepository([$product]);
        $stocks = new ProductStockFakeRepository([
            new ProductStock(1, 1, 1, 3),
        ]);

        $service = $this->makeService($products, $stocks);

        $first = $service->checkAndNotify();
        $second = $service->checkAndNotify();

        self::assertSame(1, $first->data['notified_count']);
        self::assertSame(0, $second->data['notified_count']);
    }

    public function testNoLowStockProductsReturnsEmptyRows(): void
    {
        $products = new ProductFakeRepository([]);
        $stocks = new ProductStockFakeRepository([]);

        $result = $this->makeService($products, $stocks)->checkAndNotify();

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame([], $result->data['rows']);
        self::assertSame(0, $result->data['notified_count']);
    }
}
