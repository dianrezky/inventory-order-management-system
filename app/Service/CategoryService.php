<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\CategoryRepositoryInterface;

class CategoryService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    // Sort keys accepted by the Categories list toolbar's "Sort" dropdown —
    // CategoryMySQLRepository::SORT_MAP and CategoryFakeRepository translate
    // these into the actual ORDER BY.
    public const SORT_NAME_ASC = 'name_asc';
    public const SORT_NAME_DESC = 'name_desc';
    public const SORT_CODE_ASC = 'code_asc';
    public const SORT_NEWEST = 'newest';
    public const SORT_SKUS_DESC = 'skus_desc';

    public const VALID_SORTS = [
        self::SORT_NAME_ASC,
        self::SORT_NAME_DESC,
        self::SORT_CODE_ASC,
        self::SORT_NEWEST,
        self::SORT_SKUS_DESC,
    ];

    // "Cannot delete category with associated products..." — exact wording
    // the Categories spec (§3.4) requires both for the disabled Delete
    // button's tooltip and for the server-side guard's own error message.
    public const MESSAGE_CANNOT_DELETE_HAS_PRODUCTS = 'Cannot delete category with associated products. Reassign or delete products first.';

    private $categoryRepository;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see
    // AuthService for why (avoids breaking any construction site that
    // doesn't pass one).
    public function __construct(CategoryRepositoryInterface $categoryRepository, EventLogService $eventLogService = null)
    {
        $this->categoryRepository = $categoryRepository;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'Category', $id, $description);
        }
    }

    public function listCategories($search = null, $statuses = null)
    {
        $findResult = $this->categoryRepository->findAll($search, 0, 0, $statuses);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function listActiveCategories()
    {
        $findResult = $this->categoryRepository->findAllActive();

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->categoryRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    // Categories list page: search + status filter + sort + pagination, each
    // row carrying its live assigned-SKU count.
    public function listPaged($search, $statuses, $sort, $page, $perPage, $categoryName = null, $categoryCode = null)
    {
        $sort = in_array($sort, self::VALID_SORTS, true) ? $sort : self::SORT_NAME_ASC;
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $totalResult = $this->categoryRepository->countFiltered($search, $statuses, $categoryName, $categoryCode);
        $total = $totalResult->code === Result::CODE_SUCCESS ? (int) $totalResult->data : 0;

        $itemsResult = $this->categoryRepository->findAllPaged($search, $statuses, $sort, $perPage, $offset, $categoryName, $categoryCode);
        $items = $itemsResult->code === Result::CODE_SUCCESS ? $itemsResult->data : [];

        return ['items' => $items, 'total' => $total];
    }

    // Same filters as listPaged(), but unpaginated — backs the "Export CSV"
    // toolbar action, which exports everything the current search/status
    // filter matches, not just the visible page.
    public function listAllFiltered($search, $statuses, $sort, $categoryName = null, $categoryCode = null)
    {
        $sort = in_array($sort, self::VALID_SORTS, true) ? $sort : self::SORT_NAME_ASC;
        $itemsResult = $this->categoryRepository->findAllPaged($search, $statuses, $sort, 0, 0, $categoryName, $categoryCode);

        return $itemsResult->code === Result::CODE_SUCCESS ? $itemsResult->data : [];
    }

    // KPI bar: total / active / total assigned SKUs / categories with 0 SKUs.
    public function getMetrics()
    {
        $metricsResult = $this->categoryRepository->getMetrics();

        return $metricsResult->code === Result::CODE_SUCCESS
            ? $metricsResult->data
            : ['total' => 0, 'active' => 0, 'totalAssignedSkus' => 0, 'empty' => 0];
    }

    public function createCategory($input, $actorId = null)
    {
        $result = new Result();

        try {
            $name = trim((string) ($input['name'] ?? ''));
            $nameError = $this->validateName($name);
            if ($nameError !== null) {
                return $this->validationResult($result, $nameError);
            }

            $description = $this->nullableTrim($input['description'] ?? null);
            $descriptionError = $this->validateDescription($description);
            if ($descriptionError !== null) {
                return $this->validationResult($result, $descriptionError);
            }

            $nameExistsResult = $this->categoryRepository->nameExists($name);
            if ($nameExistsResult->code !== Result::CODE_SUCCESS) {
                return $nameExistsResult;
            }
            if ($nameExistsResult->data) {
                return $this->validationResult($result, 'This name is already in use.');
            }

            $codeOrError = $this->resolveCode($input['code'] ?? null, $name, null);
            if (is_array($codeOrError) && isset($codeOrError['error'])) {
                return $this->validationResult($result, $codeOrError['error']);
            }
            $code = $codeOrError;

            $isActive = $this->parseStatus($input['status'] ?? $input['is_active'] ?? true);

            $createResult = $this->categoryRepository->create([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ]);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $findResult = $this->categoryRepository->findById($createResult->data);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            if ($findResult->data === null) {
                return $this->validationResult($result, 'Could not create the record. Please try again.');
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The category has been created.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'create', $findResult->data->id, "Created category \"{$findResult->data->name}\" ({$findResult->data->code})");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateCategory($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->categoryRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }
            if ($existingResult->data === null) {
                return $this->validationResult($result, 'Record not found.');
            }

            $name = trim((string) ($input['name'] ?? ''));
            $nameError = $this->validateName($name);
            if ($nameError !== null) {
                return $this->validationResult($result, $nameError);
            }

            $description = $this->nullableTrim($input['description'] ?? null);
            $descriptionError = $this->validateDescription($description);
            if ($descriptionError !== null) {
                return $this->validationResult($result, $descriptionError);
            }

            $nameExistsResult = $this->categoryRepository->nameExists($name, $id);
            if ($nameExistsResult->code !== Result::CODE_SUCCESS) {
                return $nameExistsResult;
            }
            if ($nameExistsResult->data) {
                return $this->validationResult($result, 'This name is already in use.');
            }

            $codeOrError = $this->resolveCode($input['code'] ?? null, $name, $id);
            if (is_array($codeOrError) && isset($codeOrError['error'])) {
                return $this->validationResult($result, $codeOrError['error']);
            }
            $code = $codeOrError;

            $isActive = $this->parseStatus($input['status'] ?? $input['is_active'] ?? $existingResult->data->isActive);

            $updateResult = $this->categoryRepository->update($id, [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ]);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $findResult = $this->categoryRepository->findById($id);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The category has been updated.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated category \"{$findResult->data->name}\" ({$findResult->data->code})");
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
            $existingResult = $this->categoryRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->validationResult($result, 'Record not found.');
            }

            $setActiveResult = $this->categoryRepository->setActive($id, $active);
            if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                return $setActiveResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The category status has been updated.';
            $result->data = null;

            $action = $active ? 'activate' : 'deactivate';
            $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " category \"{$existingResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // BR (Categories spec 3.4): a category with 1+ assigned products can
    // never be deleted, whatever the caller — this is the guard the "Delete"
    // button's disabled state and confirmation flow both rely on. The FK
    // (fk_products_category ... ON DELETE RESTRICT) is the DB-level backstop
    // if this is ever bypassed, so the two must never disagree.
    public function deleteCategory($id, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->categoryRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }
            if ($existingResult->data === null) {
                return $this->validationResult($result, 'Record not found.');
            }

            $countResult = $this->categoryRepository->countAssignedSkus($id);
            if ($countResult->code !== Result::CODE_SUCCESS) {
                return $countResult;
            }

            if ((int) $countResult->data > 0) {
                return $this->validationResult($result, self::MESSAGE_CANNOT_DELETE_HAS_PRODUCTS);
            }

            $deleteResult = $this->categoryRepository->delete($id);
            if ($deleteResult->code !== Result::CODE_SUCCESS) {
                return $deleteResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The category has been deleted.';
            $result->data = null;

            $this->logEvent($actorId, 'delete', $id, "Deleted category \"{$existingResult->data->name}\" ({$existingResult->data->code})");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // ================================================================
    // VALIDATION / CODE GENERATION HELPERS
    // ================================================================

    private function validationResult(Result $result, $message)
    {
        $result->code = Result::CODE_VALIDATION;
        $result->info = $message;
        $result->data = null;

        return $result;
    }

    private function validateName($name)
    {
        if ($name === '') {
            return 'Name is required.';
        }
        $length = mb_strlen($name);
        if ($length < 3 || $length > 80) {
            return 'Category name must be between 3 and 80 characters.';
        }

        return null;
    }

    private function validateDescription($description)
    {
        if ($description !== null && mb_strlen($description) > 250) {
            return 'Description must be at most 250 characters.';
        }

        return null;
    }

    private function nullableTrim($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    // Reads the modal's Status field (select: "active"/"inactive"), or a
    // plain boolean/1-0 for callers that already have one (e.g. defaulting a
    // brand-new category to active). Anything unrecognized falls back to
    // "active" rather than silently deactivating a record.
    private function parseStatus($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== 'inactive' && $normalized !== '0' && $normalized !== 'false';
    }

    // Returns the resolved code string, or ['error' => string] on failure.
    // $excludeId is the category's own id on update (so a code doesn't
    // collide with itself) or null on create.
    private function resolveCode($rawCode, $name, $excludeId)
    {
        $code = strtoupper(trim((string) ($rawCode ?? '')));

        if ($code === '') {
            return $this->generateUniqueCode($name);
        }

        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $code)) {
            return ['error' => 'Category code must be 3-20 characters, using only letters, numbers, and hyphens.'];
        }

        $existsResult = $this->categoryRepository->codeExists($code, $excludeId);
        if ($existsResult->code !== Result::CODE_SUCCESS) {
            return ['error' => self::MESSAGE_FAILED_FUNCTION];
        }
        if ($existsResult->data) {
            return ['error' => 'This category code is already in use.'];
        }

        return $code;
    }

    private function generateUniqueCode($name)
    {
        // "Mechanical Spare Parts" -> "MECHAN" (letters/digits only, up to 6
        // chars); an all-symbol/non-latin name falls back to "CAT" so the
        // prefix is never empty.
        $letters = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $prefix = substr($letters, 0, 6);
        if ($prefix === '') {
            $prefix = 'CAT';
        }

        for ($suffix = 1; $suffix <= 999; $suffix++) {
            $candidate = 'CAT-' . $prefix . '-' . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $existsResult = $this->categoryRepository->codeExists($candidate, null);
            if ($existsResult->code === Result::CODE_SUCCESS && !$existsResult->data) {
                return $candidate;
            }
        }

        // Astronomically unlikely (999 collisions on one prefix), but never
        // return an unchecked code.
        return 'CAT-' . $prefix . '-' . bin2hex(random_bytes(2));
    }
}
