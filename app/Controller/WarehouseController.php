<?php

namespace App\Controller;

use App\Core\Result;

class WarehouseController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_WAREHOUSES = '/warehouses';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/warehouses/list';
    public const TEMPLATE_DETAIL = 'master/warehouses/detail';
    public const TEMPLATE_FORM = 'master/warehouses/form';

    // ================================================================
    // PAGINATION CONSTANTS
    // ================================================================
    public const STOCK_PER_PAGE = 10;

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $code = $this->textFilter('code');
        $name = $this->textFilter('name');
        $location = $this->textFilter('location');
        $statuses = $this->statusFilter();
        $warehouses = $this->container->getWarehouseService()->listWarehouses(null, $statuses, $code, $name, $location);

        return $this->view(self::TEMPLATE_LIST, [
            'warehouses' => $warehouses,
            'code' => $code,
            'filterName' => $name,
            'location' => $location,
            'statuses' => $statuses,
            'currentUserRole' => $user->role->value,
        ]);
    }

    public function showAction($id)
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $user = $this->currentUser();
        $warehouse = $this->container->getWarehouseService()->findById($id);

        if ($warehouse === null) {
            return $this->notFound();
        }

        // Stock summary for the warehouse detail page (Stitch §12.4 "Warehouse Stock Detail" KPIs)
        $perPage = self::STOCK_PER_PAGE;
        $stockPage = max(1, (int) $this->requestParam('stock_page', 1));
        $stockTotal = $this->container->getProductStockService()->totalQuantityForWarehouse($id);
        $stockCount = $this->container->getProductStockService()->countByWarehouse($id);
        $stockTotalPages = max(1, (int) ceil($stockCount / $perPage));
        if ($stockPage > $stockTotalPages) {
            $stockPage = $stockTotalPages;
        }
        $stocks = $this->container->getProductStockService()->listByWarehouse($id, $perPage, ($stockPage - 1) * $perPage);

        return $this->view(self::TEMPLATE_DETAIL, [
            'warehouse' => $warehouse,
            'stocks' => $stocks,
            'stockTotal' => $stockTotal,
            'stockCount' => $stockCount,
            'stockPage' => $stockPage,
            'stockPerPage' => $perPage,
            'stockTotalPages' => $stockTotalPages,
            'currentUserRole' => $user->role->value,
        ]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('warehouses.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'warehouse' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('warehouses.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $result = $this->container->getWarehouseService()->createWarehouse($_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'warehouse' => null,
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_WAREHOUSES);
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('warehouses.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $warehouse = $this->container->getWarehouseService()->findById($id);

        if ($warehouse === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_FORM, [
            'warehouse' => $warehouse,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('warehouses.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getWarehouseService()->updateWarehouse($id, $_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            // Re-render as an EDIT of this record — passing null here turned the
            // form into a create form, so resubmitting created a duplicate.
            return $this->view(self::TEMPLATE_FORM, [
                'warehouse' => $this->container->getWarehouseService()->findById($id),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_WAREHOUSES);
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('warehouses.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getWarehouseService()->setActive($id, false, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_WAREHOUSES);
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('warehouses.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getWarehouseService()->setActive($id, true, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_WAREHOUSES);
    }
}
