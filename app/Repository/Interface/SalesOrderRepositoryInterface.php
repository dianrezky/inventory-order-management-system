<?php

namespace App\Repository\Interface;

use App\Entity\SalesOrder;

// ARCH-01: the Container memoizes one PDO connection per request, so calls made inside Database::transaction() join that transaction automatically.
interface SalesOrderRepositoryInterface
{
    public function findById($id);

    public function findAll($userId = null, $filters = [], $limit = 0, $offset = 0);

    public function countAll($userId = null, $filters = []);

    public function countByStatus($userId = null, $dateFrom = null, $dateTo = null);

    public function create($header, $items);

    public function updateStatus($id, $status, $extras = []);

    public function findForExport($from, $to, $userId = null, $warehouseId = null);

    public function lockForUpdate($id);

    /** Returns SUM of total order value, optionally scoped to user and date range. */
    public function totalRevenue($userId = null, $dateFrom = null, $dateTo = null);

    /** Returns top N customers by total revenue, optionally scoped to a user and date range. */
    public function getTopCustomers($userId = null, $limit = 5, $dateFrom = null, $dateTo = null);
}
