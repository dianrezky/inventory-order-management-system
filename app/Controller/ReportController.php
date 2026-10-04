<?php

namespace App\Controller;

use App\Core\Result;
use App\Entity\Role;

class ReportController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_REPORTS = '/reports';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_INDEX = 'reports/index';
    public const TEMPLATE_ORDERS_ONLY = 'reports/orders-only';

    // ================================================================
    // MESSAGE CONSTANTS
    // ================================================================
    public const MESSAGE_DATE_RANGE_REQUIRED = 'Please select both a start date and an end date.';

    // ================================================================
    // FILE CONSTANTS
    // ================================================================
    public const FILE_STOCK_LEDGER = 'stock-ledger.csv';
    public const FILE_ORDERS = 'orders.csv';

    // Thin by design: the filter form is read here, every number comes from
    // ReportService → ReportRepositoryInterface (no SQL in this controller).
    public function showFormAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $reportService = $this->container->getReportService();
        // The filter form is POSTed to /reports/search; nothing is read from the URL.
        $params = $reportService->normalizeParams([
            'report_type'  => $this->requestParam('report_type'),
            'date_from'    => $this->requestParam('date_from'),
            'date_to'      => $this->requestParam('date_to'),
            'warehouse_id' => $this->requestParam('warehouse_id'),
            'category_id'  => $this->requestParam('category_id'),
            'q'            => $this->requestParam('q'),
            'sort'         => $this->requestParam('sort'),
            'page'         => $this->requestParam('page'),
        ]);

        $role = $this->currentUser()->role->value;
        $permissionService = $this->getPermissionService();
        $canExport = $permissionService->roleHasPermission($role, 'reports.stock_ledger.view');
        // REPORT-01.02/B-58: the Orders export is offered to whoever may export
        // either order type — the endpoint enforces the same rule.
        $canExportOrders = $permissionService->roleHasPermission($role, 'reports.sales_orders.view')
            || $permissionService->roleHasPermission($role, 'reports.purchase_orders.view');

        // Without stock-ledger access (Sales) the inventory analytics are never
        // computed or rendered: the role only exports its own orders.
        if (!$canExport) {
            return $this->view(self::TEMPLATE_ORDERS_ONLY, [
                'dateFrom'        => $params['date_from'],
                'dateTo'          => $params['date_to'],
                'canExportOrders' => $canExportOrders,
            ]);
        }

        // Active only, like every other page's filters.
        $warehouses = $this->container->getWarehouseService()->listActiveWarehouses();
        $categories = $this->container->getCategoryService()->listActiveCategories();

        $dashboard = $reportService->getDashboard($params, $warehouses);

        return $this->view(self::TEMPLATE_INDEX, $dashboard + [
            'reportType'      => $params['report_type'],
            'dateFrom'        => $params['date_from'],
            'dateTo'          => $params['date_to'],
            'warehouseId'     => $params['warehouse_id'],
            'categoryId'      => $params['category_id'],
            'search'          => $params['search'],
            'sort'            => $params['sort'],
            'sortOptions'     => $params['sort_options'],
            'page'            => $params['page'],
            'warehouses'      => $warehouses,
            'categories'      => $categories,
            'dateRangeLabel'  => date('M d, Y', strtotime($params['date_from'])) . ' — ' . date('M d, Y', strtotime($params['date_to'])),
            'canExport'       => $canExport,
            'canExportOrders' => $canExportOrders,
        ]);
    }

    public function exportStockLedgerAction()
    {
        $authError = $this->requirePermission('reports.stock_ledger.view');
        if ($authError !== null) {
            return $authError;
        }

        // The Reports page's "Export Stock Ledger" button submits the page's own
        // filter form, so the CSV must apply the same scope as the screen:
        // date range, warehouse, category and the table's product search (q).
        $filters = [
            'date_from' => $this->dateParam('from', 'date_from'),
            'date_to' => $this->dateParam('to', 'date_to'),
            'warehouse_id' => trim((string) $this->requestParam('warehouse_id', '')),
            'category_id' => trim((string) $this->requestParam('category_id', '')),
            'q' => trim((string) $this->requestParam('q', '')),
        ];

        $exportResult = $this->container->getStockLedgerService()->findForExport($filters);

        if ($exportResult->code === Result::CODE_INTERNAL) {
            throw new \RuntimeException('Could not retrieve the stock ledger export.');
        }
        if ($exportResult->code !== Result::CODE_SUCCESS) {
            return $this->badRequest($this->t($exportResult->info));
        }

        $csv = $this->container->getCsvExportService()->exportStockLedger($exportResult->data);

        return $this->csv($csv, self::FILE_STOCK_LEDGER);
    }

    public function exportOrdersAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $currentUser = $this->currentUser();
        $permissionService = $this->getPermissionService();
        $canViewSalesOrders = $permissionService->roleHasPermission($currentUser->role->value, 'reports.sales_orders.view');
        $canViewPurchaseOrders = $permissionService->roleHasPermission($currentUser->role->value, 'reports.purchase_orders.view');

        $from = $this->dateParam('from', 'date_from');
        $to = $this->dateParam('to', 'date_to');

        // Lack of permission takes precedence over a missing date range, matching
        // the original guard order (403 before 400).
        $guardError = null;
        if (!$canViewSalesOrders && !$canViewPurchaseOrders) {
            $guardError = $this->forbidden();
        } elseif ($from === '' || $to === '') {
            $guardError = $this->badRequest($this->t(self::MESSAGE_DATE_RANGE_REQUIRED));
        }

        if ($guardError !== null) {
            return $guardError;
        }

        // Same Warehouse Location scope as the Reports screen (SO source /
        // PO destination). Category doesn't apply: one order spans many products.
        $warehouseId = (int) $this->requestParam('warehouse_id', 0);
        $warehouseFilter = $warehouseId > 0 ? $warehouseId : null;

        try {
            $rows = [];

            if ($canViewSalesOrders) {
                if ($currentUser->role === Role::Sales) {
                    $userId = $currentUser->id;
                } else {
                    $userId = null;
                }
                $rows = array_merge($rows, $this->container->getSalesOrderService()->findForExport($from, $to, $userId, $warehouseFilter));
            }

            if ($canViewPurchaseOrders) {
                $rows = array_merge($rows, $this->container->getPurchaseOrderService()->findForExport($from, $to, $warehouseFilter));
            }

            $csv = $this->container->getCsvExportService()->exportOrderStatus($rows);

            $response = $this->csv($csv, self::FILE_ORDERS);
        } catch (\App\Service\Exception\DomainException $e) {
            $response = $this->badRequest($this->t($e->getMessage()));
        }
        return $response;
    }

    private function dateParam(string $primaryKey, string $fallbackKey): string
    {
        return trim((string) $this->requestParam($primaryKey, $this->requestParam($fallbackKey, '')));
    }
}
