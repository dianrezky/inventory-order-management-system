<?php

namespace App\Controller;

use App\Core\Result;

class PurchaseOrderController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_PURCHASE_ORDERS = '/purchase-orders';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'purchase/list';
    public const TEMPLATE_DETAIL = 'purchase/detail';
    public const TEMPLATE_FORM = 'purchase/form';
    public const TEMPLATE_RECEIVE = 'purchase/receive';

    // ================================================================
    // CONFIG CONSTANTS
    // ================================================================
    public const PER_PAGE = 10;

    public function indexAction()
    {
        $authError = $this->requirePermission('purchase_orders.manage');
        if ($authError !== null) {
            return $authError;
        }

        $statuses = $this->orderStatusFilter();
        $orderNumber = $this->textFilter('order_number');
        $supplierName = $this->textFilter('supplier_name');
        $sortDirection = $this->sortDirection();
        $warehouseIds = $this->warehouseIdFilter();
        $page = max(1, (int) ($this->requestParam('page') ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $total = $this->container->getPurchaseOrderService()->countAll($statuses, null, $warehouseIds, $orderNumber, $supplierName);
        $orders = $this->container->getPurchaseOrderService()->findAll($statuses, self::PER_PAGE, $offset, null, $sortDirection, $warehouseIds, $orderNumber, $supplierName);
        $warehouses = $this->container->getWarehouseService()->listActiveWarehouses();

        return $this->view(self::TEMPLATE_LIST, [
            'orders' => $orders,
            'statuses' => $statuses,
            'orderNumber' => $orderNumber,
            'supplierName' => $supplierName,
            'sortDirection' => $sortDirection,
            'warehouseIds' => $warehouseIds,
            'warehouses' => $warehouses,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
        ]);
    }

    public function showAction($id)
    {
        $authError = $this->requirePermission('purchase_orders.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $purchaseOrder = $this->container->getPurchaseOrderService()->findById($id);

        if ($purchaseOrder === null) {
            return $this->notFound();
        }

        // Aggregate line-item totals for the detail page's "Inbound Progress" + "Total Value"
        $orderedQty = 0;
        $receivedQty = 0;
        foreach ($purchaseOrder->items as $item) {
            $orderedQty += (int) $item->qtyOrdered;
            $receivedQty += (int) $item->qtyReceived;
        }
        $orderedValue = 0.0;
        $receivedValue = 0.0;
        foreach ($purchaseOrder->items as $item) {
            $orderedValue  += (float) $item->qtyOrdered  * (float) $item->purchasePrice;
            $receivedValue += (float) $item->qtyReceived * (float) $item->purchasePrice;
        }

        // Audit log: every ledger entry linked to this PO (Stock Ledger entries)
        $ledgerEntries = $this->container->getStockLedgerService()->findByReference('PO', $id);

        return $this->view(self::TEMPLATE_DETAIL, [
            'po' => $purchaseOrder,
            'orderedQty' => $orderedQty,
            'receivedQty' => $receivedQty,
            'orderedValue' => $orderedValue,
            'receivedValue' => $receivedValue,
            'ledgerEntries' => $ledgerEntries,
            'currentUserRole' => $currentUser->role->value,
        ]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('purchase_orders.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'po' => null,
            'suppliers' => $this->container->getSupplierService()->listActiveSuppliers(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'products' => $this->container->getProductService()->listActiveProducts(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('purchase_orders.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $currentUser = $this->currentUser();
        // parseItemsFromRequest() always emits generic {product_id, qty, price}
        // keys (see BaseController) — PurchaseOrderService::create() expects
        // {product_id, qty_ordered, purchase_price}, so remap before handing
        // items off.
        $items = array_map(static function ($item) {
            return [
                'product_id'      => $item['product_id'],
                'qty_ordered'     => $item['qty'],
                'purchase_price' => $item['price'],
            ];
        }, $this->parseItemsFromRequest('item_product_id', 'item_qty_ordered', 'item_purchase_price'));
        $result = $this->container->getPurchaseOrderService()->create($_POST, $items, $currentUser->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'po' => null,
                'suppliers' => $this->container->getSupplierService()->listActiveSuppliers(),
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'products' => $this->container->getProductService()->listActiveProducts(),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_PURCHASE_ORDERS . '/' . $this->encodeId($result->data->id));
    }

    public function submitAction($id)
    {
        // PROJECT_REFERENCE.md §2.3 PO-01 "Hak Akses per Peran": Submit PO ->
        // Ordered is Admin-only (unlike create/receive/view, which
        // WarehouseStaff shares via purchase_orders.manage).
        $guardError = $this->requirePermissionWithCsrf('purchase_orders.submit');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getPurchaseOrderService()->submit($id, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->badRequest($this->t($result->info));
        }

        return $this->redirect(self::ROUTE_PURCHASE_ORDERS . '/' . $this->encodeId($id));
    }

    public function cancelAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('purchase_orders.cancel');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getPurchaseOrderService()->cancel($id, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->badRequest($this->t($result->info));
        }

        return $this->redirect(self::ROUTE_PURCHASE_ORDERS . '/' . $this->encodeId($id));
    }

    public function receiveFormAction($id)
    {
        $authError = $this->requirePermission('purchase_orders.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $purchaseOrder = $this->container->getPurchaseOrderService()->findById($id);

        if ($purchaseOrder === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_RECEIVE, [
            'po' => $purchaseOrder,
            'errors' => [],
        ]);
    }

    public function receiveAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('purchase_orders.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $purchaseOrder = $this->container->getPurchaseOrderService()->findById($id);

        if ($purchaseOrder === null) {
            return $this->notFound();
        }

        $currentUser = $this->currentUser();
        $receiptLines = [];
        foreach (($_POST['qty_now'] ?? []) as $itemId => $qty) {
            $receiptLines[(int) $itemId] = (int) $qty;
        }

        $result = $this->container->getGoodsReceiptService()->process($id, $receiptLines, $currentUser->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->view(self::TEMPLATE_RECEIVE, [
                'po' => $purchaseOrder,
                'errors' => [$this->t($result->info)],
            ])->setStatusCode($this->formErrorStatus($result));
        } else {
            $response = $this->redirect(self::ROUTE_PURCHASE_ORDERS . '/' . $this->encodeId($id));
        }

        return $response;
    }

    private function orderStatusFilter()
    {
        $validStatuses = ['Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled'];
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
