<?php

namespace App\Service;

use App\Core\CacheService;
use App\Core\Result;
use App\Entity\Product;
use App\Repository\Interface\CategoryRepositoryInterface;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\ProductStockRepositoryInterface;

class ProductService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    // CACHE-01 (§23.5): cache-aside for the single-product-by-SKU lookup only.
    // Stock is never cached (INV) — getAvailability()/getStockBreakdownByProduct()
    // always read ProductStockRepositoryInterface live.
    private const CACHE_KEY_PREFIX = 'product:';
    private const CACHE_TTL = 300;

    private $productRepository;
    private $categoryRepository;
    private $stockRepository;
    private $cacheService;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService for why.
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        ProductStockRepositoryInterface $stockRepository,
        CacheService $cacheService,
        EventLogService $eventLogService = null
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->stockRepository = $stockRepository;
        $this->cacheService = $cacheService;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'Product', $id, $description);
        }
    }

    public function listProducts($search = null, $categoryId = null)
    {
        $findResult = $this->productRepository->findAll($search, 0, 0, $categoryId);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countAll($search = null, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null)
    {
        $countResult = $this->productRepository->countAll($search, $categoryIds, $warehouseIds, $stockStatus, $sku, $productName);

        return $countResult->code === Result::CODE_SUCCESS ? $countResult->data : 0;
    }

    /**
     * Returns aggregate stock-status KPI counts: total, normal, low, out, inactive.
     * Used by the Products list page metrics bar.
     */
    public function getStockMetrics(): array
    {
        $metricsResult = $this->productRepository->countByStockStatus();

        return $metricsResult->code === Result::CODE_SUCCESS
            ? $metricsResult->data
            : ['total' => 0, 'normal' => 0, 'low' => 0, 'out' => 0, 'inactive' => 0];
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null)
    {
        $findResult = $this->productRepository->findAll($search, $limit, $offset, $categoryIds, $warehouseIds, $stockStatus, $sku, $productName);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function listActiveProducts()
    {
        $findResult = $this->productRepository->findAllActive();

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->productRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    public function findBySku($sku)
    {
        $cacheKey = $this->cacheKeyForSku($sku);
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== null) {
            return Product::fromArray($cached);
        }

        $findResult = $this->productRepository->findBySku($sku);
        if ($findResult->code !== Result::CODE_SUCCESS || $findResult->data === null) {
            return null;
        }

        $product = $findResult->data;
        $this->cacheService->set($cacheKey, $product->toArray(), self::CACHE_TTL);

        return $product;
    }

    public function getAvailability($sku)
    {
        $findResult = $this->productRepository->findBySku($sku);
        if ($findResult->code !== Result::CODE_SUCCESS || $findResult->data === null) {
            return null;
        }

        $product = $findResult->data;

        $stocksResult = $this->stockRepository->findAllWithProduct($product->id);
        $stocks = $stocksResult->code === Result::CODE_SUCCESS ? $stocksResult->data : [];

        $total       = 0;
        $availability = [];

        foreach ($stocks as $stock) {
            if (!$stock['warehouse_active']) {
                continue;
            }
            $availability[] = [
                'warehouse_id'   => (int) $stock['warehouse_id'],
                'warehouse_code' => $stock['warehouse_code'],
                'warehouse_name' => $stock['warehouse_name'],
                'quantity'       => (int) $stock['quantity'],
            ];
            $total += (int) $stock['quantity'];
        }

        return [
            'sku'          => $product->sku,
            'product_id'   => $product->id,
            'name'         => $product->name,
            'unit'         => $product->unit,
            'available'    => $total > 0,
            'total_stock'  => $total,
            'availability' => $availability,
        ];
    }

    // Returns per-warehouse stock breakdown for the product detail page.
    // Includes the total stock (sum across active warehouses) and a low-stock flag
    // so the view can render the Stitch-style "Network Stock Position" card.
    public function getStockBreakdownByProduct($productId)
    {
        $stocksResult = $this->stockRepository->findAllWithProduct($productId);
        $stocks = $stocksResult->code === Result::CODE_SUCCESS ? $stocksResult->data : [];

        $breakdown = [];
        $total = 0;
        $hasActive = false;
        foreach ($stocks as $stock) {
            $qty = (int) $stock['quantity'];
            $total += $qty;
            if ((int) $stock['warehouse_active'] === 1) {
                $hasActive = true;
            }
            $breakdown[] = [
                'warehouse_id'   => (int) $stock['warehouse_id'],
                'warehouse_code' => $stock['warehouse_code'],
                'warehouse_name' => $stock['warehouse_name'],
                'warehouse_active' => (int) $stock['warehouse_active'],
                'quantity'       => $qty,
            ];
        }

        // Sort active warehouses first, then by warehouse code for stable display.
        usort($breakdown, static function ($a, $b) {
            if ($a['warehouse_active'] !== $b['warehouse_active']) {
                return $b['warehouse_active'] <=> $a['warehouse_active'];
            }
            return strcmp($a['warehouse_code'], $b['warehouse_code']);
        });

        return [
            'total'      => $total,
            'has_stock'  => $total > 0,
            'breakdown'  => $breakdown,
            'has_active' => $hasActive,
        ];
    }

    public function createProduct($input, $imagePath = null, $actorId = null, ?callable $initialStockHook = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateCreatePayload($input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);
            $data['image_path'] = $imagePath;

            $createResult = $this->productRepository->create($data);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $findResult = $this->productRepository->findById($createResult->data);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $this->invalidateSkuCache($findResult->data->sku);

            // Hook for initial-stock allocation: the controller passes a closure
            // that has access to StockLedgerService/ProductStockService. The
            // service itself doesn't reach into other services — keeps it a
            // thin orchestrator over the product repository.
            if ($initialStockHook !== null) {
                $initialStockHook($findResult->data->id, $actorId);
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The product has been created.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'create', $findResult->data->id, "Created product \"{$findResult->data->sku}\" — {$findResult->data->name}");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateProduct($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->productRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $validation = $this->validateUpdatePayload($id, $input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);

            $updateResult = $this->productRepository->update($id, $data);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $findResult = $this->productRepository->findById($id);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            // Invalidate both the old SKU (in case it changed) and the current one.
            $this->invalidateSkuCache($existingResult->data->sku);
            $this->invalidateSkuCache($findResult->data->sku);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The product has been updated.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated product \"{$findResult->data->sku}\" — {$findResult->data->name}");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateImagePath($id, $imagePath)
    {
        $result = new Result();

        try {
            $existingResult = $this->productRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $updateResult = $this->productRepository->updateImagePath($id, $imagePath);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $this->invalidateSkuCache($existingResult->data->sku);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The product image has been updated.';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function setActive($id, $active, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->productRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $setActiveResult = $this->productRepository->setActive($id, $active);
            if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                return $setActiveResult;
            }

            $this->invalidateSkuCache($existingResult->data->sku);

            $action = $active ? 'activate' : 'deactivate';
            $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " product \"{$existingResult->data->sku}\"");

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The product status has been updated.';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Validation helpers — return null (pass) or a failure Result (fail)

    private function validateCreatePayload($input)
    {
        $sku      = strtoupper(trim((string) ($input['sku'] ?? '')));
        $name     = trim((string) ($input['name'] ?? ''));
        $unit     = trim((string) ($input['unit'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $purchasePrice = (string) ($input['purchase_price'] ?? '0');
        $salePrice    = (string) ($input['sale_price'] ?? '0');
        $reorderPoint = (int) ($input['reorder_point'] ?? 0);

        if ($sku === '') {
            return $this->validationResult('SKU is required.');
        }

        if (strlen($sku) > 30) {
            return $this->validationResult('SKU must be 30 characters or fewer.');
        }

        if ($name === '') {
            return $this->validationResult('Product name is required.');
        }

        if (strlen($name) < 3 || strlen($name) > 150) {
            return $this->validationResult('Product name must be between 3 and 150 characters.');
        }

        if (strlen($description) > 500) {
            return $this->validationResult('Description must be 500 characters or fewer.');
        }

        if ($unit === '') {
            return $this->validationResult('Unit of measure is required.');
        }

        if ($categoryId <= 0) {
            return $this->validationResult('Please select a category.');
        }

        if (!is_numeric($purchasePrice) || (float) $purchasePrice < 0) {
            return $this->validationResult('Please enter a valid, non-negative purchase price.');
        }

        if (!is_numeric($salePrice) || (float) $salePrice < 0) {
            return $this->validationResult('Please enter a valid, non-negative selling price.');
        }

        if ((float) $salePrice < (float) $purchasePrice) {
            return $this->validationResult('Selling price must be greater than or equal to purchase price.');
        }

        // PRD-01.04: reorder point >= 0 (0 disables the low-stock alert)
        if ($reorderPoint < 0) {
            return $this->validationResult('Minimum stock threshold cannot be negative.');
        }

        $skuExistsResult = $this->productRepository->skuExists($sku);
        if ($skuExistsResult->code !== Result::CODE_SUCCESS) {
            return $skuExistsResult;
        }

        if ($skuExistsResult->data) {
            return $this->validationResult('This SKU is already in use.');
        }

        $categoryResult = $this->categoryRepository->findById($categoryId);
        if ($categoryResult->code !== Result::CODE_SUCCESS) {
            return $categoryResult;
        }

        if ($categoryResult->data === null) {
            return $this->validationResult('Selected category does not exist.');
        }

        return null;
    }

    private function validateUpdatePayload($id, $input)
    {
        $sku      = strtoupper(trim((string) ($input['sku'] ?? '')));
        $name     = trim((string) ($input['name'] ?? ''));
        $unit     = trim((string) ($input['unit'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $purchasePrice = (string) ($input['purchase_price'] ?? '0');
        $salePrice    = (string) ($input['sale_price'] ?? '0');
        $reorderPoint = (int) ($input['reorder_point'] ?? 0);

        if ($sku === '') {
            return $this->validationResult('SKU is required.');
        }

        if (strlen($sku) > 30) {
            return $this->validationResult('SKU must be 30 characters or fewer.');
        }

        if ($name === '') {
            return $this->validationResult('Product name is required.');
        }

        if (strlen($name) < 3 || strlen($name) > 150) {
            return $this->validationResult('Product name must be between 3 and 150 characters.');
        }

        if (strlen($description) > 500) {
            return $this->validationResult('Description must be 500 characters or fewer.');
        }

        if ($unit === '') {
            return $this->validationResult('Unit of measure is required.');
        }

        if ($categoryId <= 0) {
            return $this->validationResult('Please select a category.');
        }

        if (!is_numeric($purchasePrice) || (float) $purchasePrice < 0) {
            return $this->validationResult('Please enter a valid, non-negative purchase price.');
        }

        if (!is_numeric($salePrice) || (float) $salePrice < 0) {
            return $this->validationResult('Please enter a valid, non-negative selling price.');
        }

        if ((float) $salePrice < (float) $purchasePrice) {
            return $this->validationResult('Selling price must be greater than or equal to purchase price.');
        }

        // PRD-01.04: reorder point >= 0 (0 disables the low-stock alert)
        if ($reorderPoint < 0) {
            return $this->validationResult('Minimum stock threshold cannot be negative.');
        }

        $skuExistsResult = $this->productRepository->skuExists($sku, $id);
        if ($skuExistsResult->code !== Result::CODE_SUCCESS) {
            return $skuExistsResult;
        }

        if ($skuExistsResult->data) {
            return $this->validationResult('This SKU is already in use.');
        }

        $categoryResult = $this->categoryRepository->findById($categoryId);
        if ($categoryResult->code !== Result::CODE_SUCCESS) {
            return $categoryResult;
        }

        if ($categoryResult->data === null) {
            return $this->validationResult('Selected category does not exist.');
        }

        return null;
    }

    private function buildCreateData($input)
    {
        $purchasePrice = (string) ($input['purchase_price'] ?? '0');
        $salePrice     = (string) ($input['sale_price'] ?? '0');

        return [
            'sku'      => strtoupper(trim((string) ($input['sku'] ?? ''))),
            'barcode'  => trim((string) ($input['barcode'] ?? '')),
            'name'     => trim((string) ($input['name'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'category_id' => (int) ($input['category_id'] ?? 0),
            'unit'     => trim((string) ($input['unit'] ?? '')),
            'purchase_price' => number_format((float) $purchasePrice, 2, '.', ''),
            'sale_price'     => number_format((float) $salePrice, 2, '.', ''),
            'reorder_point'  => (int) ($input['reorder_point'] ?? 0),
            'is_active' => isset($input['is_active']) ? (bool) $input['is_active'] : true,
        ];
    }

    private function cacheKeyForSku($sku)
    {
        return self::CACHE_KEY_PREFIX . strtoupper((string) $sku);
    }

    private function invalidateSkuCache($sku)
    {
        $this->cacheService->delete($this->cacheKeyForSku($sku));
    }

    private function notFoundResult()
    {
        $r = new Result();
        $r->code = Result::CODE_VALIDATION;
        $r->info = 'Record not found.';
        $r->data = null;

        return $r;
    }

    private function validationResult($info)
    {
        $r = new Result();
        $r->code = Result::CODE_VALIDATION;
        $r->info = $info;
        $r->data = null;

        return $r;
    }
}
