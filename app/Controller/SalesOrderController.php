<?php

namespace App\Controller;

use App\Core\Result;
use App\Entity\Role;

class SalesOrderController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_SALES_ORDERS = '/sales-orders';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'sales/list';
    public const TEMPLATE_DETAIL = 'sales/detail';
    public const TEMPLATE_FORM = 'sales/form';

    // ================================================================
    // CONFIG CONSTANTS
    // ================================================================
    public const PER_PAGE = 10;

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $currentUser = $this->currentUser();

        if ($currentUser->role === Role::Sales) {
            $userId = $currentUser->id;
        } else {
            $userId = null;
        }

        $statuses = $this->orderStatusFilter();
        $orderNumber = $this->textFilter('order_number');
        $customerName = $this->textFilter('customer_name');
        $sortDirection = $this->sortDirection();
        $page = max(1, (int) ($this->requestParam('page') ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $warehouseIds = $this->warehouseIdFilter();

        $filters = [];
        if ($statuses !== null) {
            $filters['status'] = $statuses;
        }
        if ($orderNumber !== null) {
            $filters['order_number'] = $orderNumber;
        }
        if ($customerName !== null) {
            $filters['customer_name'] = $customerName;
        }
        if ($warehouseIds !== null) {
            $filters['warehouse_id'] = $warehouseIds;
        }
        $filters['sort'] = $sortDirection;

        $service = $this->container->getSalesOrderService();
        $total = $service->countSalesOrders($userId, $filters);
        $orders = $service->listSalesOrders($userId, $filters, self::PER_PAGE, $offset);
        $warehouses = $this->container->getWarehouseService()->listActiveWarehouses();

        return $this->view(self::TEMPLATE_LIST, [
            'orders' => $orders,
            'statuses' => $statuses,
            'orderNumber' => $orderNumber,
            'customerName' => $customerName,
            'sortDirection' => $sortDirection,
            'warehouseIds' => $warehouseIds,
            'warehouses' => $warehouses,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'currentUser' => $currentUser,
        ]);
    }

    public function showAction($id)
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        $currentUser = $this->currentUser();
        $salesOrder = null;
        if ($id !== null) {
            $salesOrder = $this->container->getSalesOrderService()->findById($id);
        }

        $guardError = null;
        if ($salesOrder === null) {
            $guardError = $this->notFound();
        } elseif ($currentUser->role === Role::Sales && $salesOrder->createdBy !== $currentUser->id) {
            $guardError = $this->forbidden();
        }

        if ($guardError !== null) {
            return $guardError;
        }

        return $this->view(self::TEMPLATE_DETAIL, [
            'so' => $salesOrder,
            'currentUser' => $currentUser,
            'ledgerEntries' => $this->container->getStockLedgerService()->findByReference('SO', $id),
        ]);
    }

    public function createFormAction()
    {
        // PROJECT_REFERENCE.md §1.2/SO-01: only Admin and Sales may create a
        // sales order; WarehouseStaff's role is limited to goods issue/receipt.
        // sales_orders.menu already carries exactly that Admin+Sales grant.
        $authError = $this->requirePermission('sales_orders.menu');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'so' => null,
            'customers' => $this->container->getCustomerService()->listActiveCustomers(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'products' => $this->container->getProductService()->listActiveProducts(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('sales_orders.menu');
        if ($guardError !== null) {
            return $guardError;
        }

        $currentUser = $this->currentUser();
        // parseItemsFromRequest() always emits generic {product_id, qty, price}
        // keys (see BaseController) — SalesOrderService::create() expects
        // {product_id, qty, sale_price}, so remap before handing items off.
        // (Without this remap, sale_price is silently lost and every line
        // saves at 0 — see PurchaseOrderController::storeAction() for the
        // same remap on the purchase-order side.)
        $items = array_map(static function ($item) {
            return [
                'product_id' => $item['product_id'],
                'qty'        => $item['qty'],
                'sale_price' => $item['price'],
            ];
        }, $this->parseItemsFromRequest('item_product_id', 'item_qty', 'item_sale_price'));
        $result = $this->container->getSalesOrderService()->create($_POST, $items, $currentUser->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'so' => null,
                'customers' => $this->container->getCustomerService()->listActiveCustomers(),
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'products' => $this->container->getProductService()->listActiveProducts(),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($result->data->id));
    }

    public function editFormAction($id)
    {
        $guardError = $this->requirePermission('sales_orders.menu');
        if ($guardError !== null) {
            return $guardError;
        }
        $currentUser = $this->currentUser();
        $draftResult = $this->container->getSalesOrderService()->findEditableDraft($this->decodeId($id), $currentUser->id, $currentUser->role === Role::Admin);
        if ($draftResult->code === Result::CODE_INTERNAL) {
            throw new \RuntimeException('Could not load the Draft sales order.');
        }
        if ($draftResult->code !== Result::CODE_SUCCESS) {
            $response = $draftResult->info === \App\Service\SalesOrderService::MESSAGE_NOT_FOUND ? $this->notFound() : $this->forbidden();
        } else {
            $response = $this->salesOrderEditForm($draftResult->data, [], []);
        }
        return $response;
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('sales_orders.menu');
        if ($guardError !== null) {
            return $guardError;
        }
        $currentUser = $this->currentUser();
        $id = $this->decodeId($id);
        $draftResult = $this->container->getSalesOrderService()->findEditableDraft($id, $currentUser->id, $currentUser->role === Role::Admin);
        if ($draftResult->code === Result::CODE_INTERNAL) {
            throw new \RuntimeException('Could not load the Draft sales order.');
        }
        if ($draftResult->code !== Result::CODE_SUCCESS) {
            return $draftResult->info === \App\Service\SalesOrderService::MESSAGE_NOT_FOUND ? $this->notFound() : $this->forbidden();
        }
        $items = array_map(static function ($item) {
            return ['product_id' => $item['product_id'], 'qty' => $item['qty'], 'sale_price' => $item['price']];
        }, $this->parseItemsFromRequest('item_product_id', 'item_qty', 'item_sale_price'));
        $updateResult = $this->container->getSalesOrderService()->updateDraft($id, $_POST, $items, $currentUser->id, $currentUser->role === Role::Admin);
        if ($updateResult->code !== Result::CODE_SUCCESS) {
            $response = $this->salesOrderEditForm($draftResult->data, [$this->t($updateResult->info)], $_POST)->setStatusCode($this->formErrorStatus($updateResult));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    private function salesOrderEditForm($salesOrder, $errors, $old)
    {
        return $this->view(self::TEMPLATE_FORM, [
            'so' => $salesOrder,
            'customers' => $this->container->getCustomerService()->listActiveCustomers(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'products' => $this->container->getProductService()->listActiveProducts(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function submitAction($id)
    {
        $guardError = $this->requireAuthWithCsrf();
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $result = $this->container->getSalesOrderService()->submitForApproval($id, $currentUser->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->badRequest($this->t($result->info));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    public function approveAction($id)
    {
        // B-33 / SOD-01: a Sales user is told why the approval is refused
        $guardError = $this->requirePermissionWithCsrf('sales_orders.approve', 'Only an administrator can approve a sales order.');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $userIsAdmin = $currentUser->role === Role::Admin;
        $result = $this->container->getSalesOrderService()->approve($id, $currentUser->id, $userIsAdmin);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->badRequest($this->t($result->info));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    public function rejectAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('sales_orders.approve', 'Only an administrator can reject a sales order.');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            $reason = null;
        }

        $userIsAdmin = $currentUser->role === Role::Admin;
        $result = $this->container->getSalesOrderService()->reject($id, $currentUser->id, $userIsAdmin, $reason);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->badRequest($this->t($result->info));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    public function cancelAction($id)
    {
        $guardError = $this->requireAuthWithCsrf();
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $userIsAdmin = $currentUser->role === Role::Admin;
        $result = $this->container->getSalesOrderService()->cancel($id, $currentUser->id, $userIsAdmin);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->badRequest($this->t($result->info));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    public function issueAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('sales_orders.issue');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $result = $this->container->getGoodsIssueService()->issue($id, $currentUser->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->badRequest($this->t($result->info));
        } else {
            $response = $this->redirect(self::ROUTE_SALES_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    private function orderStatusFilter()
    {
        $validStatuses = ['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'];
        $raw = $this->requestParam('status');
        if ($raw === null) { return null; }
        if (is_array($raw)) {
            $valid = array_filter($raw, static fn ($v) => in_array($v, $validStatuses, true));
            return count($valid) > 0 ? array_values($valid) : null;
        }
        $s = trim((string) $raw);
        return in_array($s, $validStatuses, true) ? [$s] : null;
    }

    private function sortDirection(): string
    {
        $sort = strtolower(trim((string) ($this->requestParam('sort') ?? '')));

        return $sort === 'asc' ? 'asc' : 'desc';
    }
}
