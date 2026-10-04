<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Product;
use App\Repository\Interface\ProductRepositoryInterface;
use DateTimeImmutable;

// In-memory fake backing the unit test suite (ADR-001 §3); no database dependency.
class ProductFakeRepository implements ProductRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($products = [])
    {
        foreach ($products as $product) {
            $this->byId[$product->id] = $product;
            $this->nextId = max($this->nextId, $product->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find product';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findBySku($sku)
    {
        $found = null;

        foreach ($this->byId as $product) {
            if ($product->sku === $sku) {
                $found = $product;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find product';
        $result->data = $found;

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null, $sort = 'name_asc')
    {
        // $warehouseIds, $stockStatus, $sku and $productName are accepted for interface
        // compatibility but not modeled here — tests needing those filters should use
        // ProductMySQLRepository or integration tests instead.
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter(
                $all,
                function ($p) use ($search) {
                    return stripos($p->sku, $search) !== false
                        || stripos($p->name, $search) !== false;
                }
            ));
        }

        if ($categoryIds !== null && count($categoryIds) > 0) {
            $all = array_values(array_filter(
                $all,
                function ($p) use ($categoryIds) { return in_array($p->categoryId, $categoryIds, true); }
            ));
        }

        if (!in_array($sort, ['name_asc', 'name_desc', 'sku_asc', 'sku_desc'], true)) {
            $sort = 'name_asc';
        }
        $field = str_starts_with($sort, 'sku_') ? 'sku' : 'name';
        $direction = in_array($sort, ['name_desc', 'sku_desc'], true) ? -1 : 1;
        usort($all, static function ($a, $b) use ($field, $direction) {
            return $direction * ($a->{$field} <=> $b->{$field}) ?: $a->id <=> $b->id;
        });

        if ($offset > 0 || $limit > 0) {
            $all = array_slice($all, $offset, $limit > 0 ? $limit : null);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list products';
        $result->data = $all;

        return $result;
    }

    public function countAll($search = null, $categoryIds = null, $warehouseIds = null, $stockStatus = null, $sku = null, $productName = null)
    {
        // See findAll() above — $warehouseIds, $stockStatus, $sku and $productName not modeled in this fake.
        $all = array_values($this->byId);
        if ($search !== null && $search !== '') {
            $all = array_values(array_filter(
                $all,
                function ($p) use ($search) {
                    return stripos($p->sku, $search) !== false
                        || stripos($p->name, $search) !== false;
                }
            ));
        }

        if ($categoryIds !== null && count($categoryIds) > 0) {
            $all = array_values(array_filter(
                $all,
                function ($p) use ($categoryIds) { return in_array($p->categoryId, $categoryIds, true); }
            ));
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count products';
        $result->data = count($all);

        return $result;
    }

    public function findAllActive()
    {
        $allResult = $this->findAll();

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list active products';
        $result->data = array_values(array_filter($allResult->data, function ($p) { return $p->isActive; }));

        return $result;
    }

    public function skuExists($sku, $excludeId = null)
    {
        $exists = false;

        foreach ($this->byId as $p) {
            if ($excludeId !== null && $p->id === $excludeId) {
                continue;
            }
            if (strcasecmp($p->sku, $sku) === 0) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check SKU';
        $result->data = $exists;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Product(
            $id,
            (string) $data['sku'],
            (string) $data['name'],
            (int) $data['category_id'],
            (string) $data['unit'],
            (string) $data['purchase_price'],
            (string) $data['sale_price'],
            (int) $data['reorder_point'],
            $data['image_path'] ?? null,
            isset($data['is_active']) ? (bool) $data['is_active'] : true,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            null,
            !empty($data['barcode']) ? (string) $data['barcode'] : null,
            !empty($data['description']) ? (string) $data['description'] : null,
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create product';
        $result->data = $id;

        return $result;
    }

    public function update($id, $data)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new Product(
            $existing->id,
            (string) $data['sku'],
            (string) $data['name'],
            (int) $data['category_id'],
            (string) $data['unit'],
            (string) $data['purchase_price'],
            (string) $data['sale_price'],
            (int) $data['reorder_point'],
            $existing->imagePath,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable(),
            $existing->categoryName,
            !empty($data['barcode']) ? (string) $data['barcode'] : null,
            !empty($data['description']) ? (string) $data['description'] : null,
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update product';
        $result->data = null;

        return $result;
    }

    public function updateImagePath($id, $imagePath)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new Product(
            $existing->id,
            $existing->sku,
            $existing->name,
            $existing->categoryId,
            $existing->unit,
            $existing->purchasePrice,
            $existing->salePrice,
            $existing->reorderPoint,
            $imagePath,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable(),
            $existing->categoryName,
            $existing->barcode,
            $existing->description,
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update product image';
        $result->data = null;

        return $result;
    }

    public function setActive($id, $active)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new Product(
            $existing->id,
            $existing->sku,
            $existing->name,
            $existing->categoryId,
            $existing->unit,
            $existing->purchasePrice,
            $existing->salePrice,
            $existing->reorderPoint,
            $existing->imagePath,
            $active,
            $existing->createdAt,
            new DateTimeImmutable(),
            $existing->categoryName,
            $existing->barcode,
            $existing->description,
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update product status';
        $result->data = null;

        return $result;
    }

    public function countByStockStatus()
    {
        // Fake: returns empty counts — real data comes from MySQL repository
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count products by stock status';
        $result->data = ['total' => 0, 'normal' => 0, 'low' => 0, 'out' => 0, 'inactive' => 0];

        return $result;
    }

    public function findLowStock()
    {
        // Fake shortcut: the real implementation queries product_stocks; here every active product is returned.
        $allResult = $this->findAll();

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list low stock products';
        $result->data = array_values(array_filter(
            $allResult->data,
            function ($p) { return $p->isActive; },
        ));

        return $result;
    }
}
