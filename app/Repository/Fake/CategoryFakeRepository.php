<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Category;
use App\Repository\Interface\CategoryRepositoryInterface;
use App\Service\CategoryService;
use DateTimeImmutable;

// In-memory fake backing the unit test suite; no database dependency.
class CategoryFakeRepository implements CategoryRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    // productCountByCategory lets a test seed how many products are
    // "assigned" to a category id without needing a real ProductRepository —
    // defaults to 0 (empty) for any category not listed here.
    private $productCountByCategory;

    public function __construct($categories = [], $productCountByCategory = [])
    {
        foreach ($categories as $category) {
            $this->byId[$category->id] = $category;
            $this->nextId = max($this->nextId, $category->id + 1);
        }
        $this->productCountByCategory = $productCountByCategory;
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find category';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null)
    {
        $all = $this->filtered($search, $statuses);

        usort($all, function ($a, $b) { return $a->name <=> $b->name; });

        if ($limit > 0) {
            $all = array_slice($all, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list categories';
        $result->data = $all;

        return $result;
    }

    public function findAllPaged($search, $statuses, $sort, $limit, $offset, $categoryName = null, $categoryCode = null)
    {
        $all = $this->filtered($search, $statuses, $categoryName, $categoryCode);

        foreach ($all as $c) {
            $c->assignedSkuCount = $this->productCountByCategory[$c->id] ?? 0;
        }

        usort($all, function ($a, $b) use ($sort) {
            return match ($sort) {
                CategoryService::SORT_NAME_DESC => $b->name <=> $a->name,
                CategoryService::SORT_CODE_ASC => $a->code <=> $b->code,
                CategoryService::SORT_NEWEST => ($b->createdAt ?? new DateTimeImmutable('@0')) <=> ($a->createdAt ?? new DateTimeImmutable('@0')),
                CategoryService::SORT_SKUS_DESC => $b->assignedSkuCount <=> $a->assignedSkuCount ?: $a->name <=> $b->name,
                default => $a->name <=> $b->name,
            };
        });

        if ($limit > 0) {
            $all = array_slice($all, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list categories';
        $result->data = $all;

        return $result;
    }

    public function countFiltered($search, $statuses, $categoryName = null, $categoryCode = null)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count categories';
        $result->data = count($this->filtered($search, $statuses, $categoryName, $categoryCode));

        return $result;
    }

    public function getMetrics()
    {
        $all = array_values($this->byId);
        $active = 0;
        $totalAssignedSkus = 0;
        $empty = 0;

        foreach ($all as $c) {
            if ($c->isActive) {
                $active++;
            }
            $skuCount = $this->productCountByCategory[$c->id] ?? 0;
            $totalAssignedSkus += $skuCount;
            if ($skuCount === 0) {
                $empty++;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to compute category metrics';
        $result->data = [
            'total' => count($all),
            'active' => $active,
            'totalAssignedSkus' => $totalAssignedSkus,
            'empty' => $empty,
        ];

        return $result;
    }

    public function countAssignedSkus($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count assigned SKUs';
        $result->data = $this->productCountByCategory[$id] ?? 0;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Category(
            $id,
            (string) $data['code'],
            (string) $data['name'],
            $data['description'] ?? null,
            true,
            0,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create category';
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

        $this->byId[$id] = new Category(
            $existing->id,
            (string) $data['code'],
            (string) $data['name'],
            $data['description'] ?? null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $existing->isActive,
            $existing->assignedSkuCount,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update category';
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

        $this->byId[$id] = new Category(
            $existing->id,
            $existing->code,
            $existing->name,
            $existing->description,
            $active,
            $existing->assignedSkuCount,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update category status';
        $result->data = null;

        return $result;
    }

    public function delete($id)
    {
        $result = new Result();

        if (($this->productCountByCategory[$id] ?? 0) > 0) {
            // Mirrors the real DB's fk_products_category ... ON DELETE
            // RESTRICT — the fake should fail the same way a bypassed
            // service-layer guard would fail for real, not silently succeed.
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        unset($this->byId[$id]);

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to delete category';
        $result->data = null;

        return $result;
    }

    public function findAllActive()
    {
        $allResult = $this->findAll();

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list active categories';
        $result->data = array_values(array_filter($allResult->data, function ($c) { return $c->isActive; }));

        return $result;
    }

    public function nameExists($name, $excludeId = null)
    {
        $exists = false;

        foreach ($this->byId as $c) {
            if ($excludeId !== null && $c->id === $excludeId) {
                continue;
            }
            if (strcasecmp($c->name, $name) === 0) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check category name';
        $result->data = $exists;

        return $result;
    }

    public function codeExists($code, $excludeId = null)
    {
        $exists = false;

        foreach ($this->byId as $c) {
            if ($excludeId !== null && $c->id === $excludeId) {
                continue;
            }
            if (strcasecmp($c->code, $code) === 0) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check category code';
        $result->data = $exists;

        return $result;
    }

    private function filtered($search, $statuses, $categoryName = null, $categoryCode = null)
    {
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($c) use ($search) {
                return stripos($c->name, $search) !== false
                    || stripos($c->description ?? '', $search) !== false
                    || stripos($c->code, $search) !== false;
            }));
        }

        if ($categoryName !== null && $categoryName !== '') {
            $all = array_values(array_filter($all, function ($c) use ($categoryName) {
                return stripos($c->name, $categoryName) !== false;
            }));
        }

        if ($categoryCode !== null && $categoryCode !== '') {
            $all = array_values(array_filter($all, function ($c) use ($categoryCode) {
                return stripos($c->code, $categoryCode) !== false;
            }));
        }

        if ($statuses !== null && count($statuses) > 0) {
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = true; }
                elseif ($s === 'inactive') { $isActiveValues[] = false; }
            }
            if (!empty($isActiveValues)) {
                $all = array_values(array_filter($all, function ($c) use ($isActiveValues) {
                    return in_array($c->isActive, $isActiveValues, true);
                }));
            }
        }

        return $all;
    }
}
