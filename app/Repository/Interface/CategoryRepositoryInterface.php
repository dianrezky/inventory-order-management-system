<?php

namespace App\Repository\Interface;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface CategoryRepositoryInterface
{
    public function findById($id);

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null);

    // Categories list page: search + status filter + sort + pagination, each
    // row annotated with its live assigned-SKU count (LEFT JOIN products).
    // $sort is one of the CategoryService::SORT_* keys.
    public function findAllPaged($search, $statuses, $sort, $limit, $offset, $categoryName = null, $categoryCode = null);

    public function countFiltered($search, $statuses, $categoryName = null, $categoryCode = null);

    // KPI bar: total / active / total assigned SKUs / categories with 0 SKUs.
    public function getMetrics();

    public function countAssignedSkus($id);

    public function create($data);

    public function update($id, $data);

    public function setActive($id, $active);

    // Hard delete — only ever called by the service after it has confirmed
    // the category has 0 assigned SKUs (CLAUDE.md data-integrity rule).
    public function delete($id);

    public function findAllActive();

    public function nameExists($name, $excludeId = null);

    public function codeExists($code, $excludeId = null);
}
