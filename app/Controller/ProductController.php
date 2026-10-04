<?php

namespace App\Controller;

use App\Core\Result;

class ProductController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_PRODUCTS = '/products';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/products/list';
    public const TEMPLATE_DETAIL = 'master/products/detail';
    public const TEMPLATE_FORM = 'master/products/form';

    // ================================================================
    // CONFIG CONSTANTS
    // ================================================================
    public const PER_PAGE = 10;
    // Must match the "Rows per page" select in views/master/products/list.php.
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    public const FLASH_ERROR = 'flash.products.error';

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $currentUser = $this->currentUser();
        $sku = $this->textFilter('sku');
        $productName = $this->textFilter('product_name');
        $categoryIds  = $this->categoryFilter();
        $warehouseIds = $this->warehouseFilter();
        $stockStatus = $this->stockStatusFilter();
        $page = max(1, (int) ($this->requestParam('page') ?? 1));
        $perPage = (int) $this->requestParam('per_page', self::PER_PAGE);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE;
        }
        $offset = ($page - 1) * $perPage;

        // KPI metrics — always computed on full dataset (unfiltered)
        $metrics = $this->container->getProductService()->getStockMetrics();

        // B-42: the default list hides deactivated products (a deactivated
        // catalogue reads as empty); they stay reachable via "Inactive".
        $queryStockStatus = $stockStatus;
        if ($queryStockStatus === null) {
            $queryStockStatus = 'active';
        }

        $total = $this->container->getProductService()->countAll(null, $categoryIds, $warehouseIds, $queryStockStatus, $sku, $productName);
        $products = $this->container->getProductService()->findAll(null, $perPage, $offset, $categoryIds, $warehouseIds, $queryStockStatus, $sku, $productName);

        // Compute per-product stock for the list view's stock column. When a
        // warehouse filter is active, show that warehouse's quantity instead of
        // the aggregate total (see products/list.php header label switch) —
        // mirrors the Stitch §5.5 Products list spec otherwise: "Total Stock
        // (right) | Reorder Pt (right) | Stock Status".
        $productStocks = [];
        foreach ($products as $product) {
            $breakdown = $this->container->getProductService()->getStockBreakdownByProduct($product->id);

            // Sum only the selected warehouses (one or many) — the Stock Status
            // filter's HAVING does the same, so the column and the filter agree.
            if ($warehouseIds !== null && count($warehouseIds) > 0) {
                $selectedIds = array_map('intval', $warehouseIds);
                $productStocks[$product->id] = 0;
                foreach ($breakdown['breakdown'] as $row) {
                    if (in_array((int) $row['warehouse_id'], $selectedIds, true)) {
                        $productStocks[$product->id] += (int) $row['quantity'];
                    }
                }
            } else {
                $productStocks[$product->id] = $breakdown['total'];
            }
        }

        return $this->view(self::TEMPLATE_LIST, [
            'products' => $products,
            'productStocks' => $productStocks,
            'sku' => $sku,
            'productName' => $productName,
            'categoryIds' => $categoryIds,
            'warehouseIds' => $warehouseIds,
            'stockStatus' => $stockStatus,
            'categories' => $this->container->getCategoryService()->listActiveCategories(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'page' => $page,
            'perPage' => $perPage,
            'flashError' => $this->pullFlash(self::FLASH_ERROR),
            'total' => $total,
            'metrics' => $metrics,
            'currentUserRole' => $currentUser->role->value,
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
        $product = null;
        if ($id !== null) {
            $product = $this->container->getProductService()->findById($id);
        }

        if ($product === null) {
            return $this->notFound();
        }

        // Per-warehouse stock breakdown for the Stitch-style
        // "Network Stock Position" / "Stock by Warehouse" card on the detail page
        $stockBreakdown = $this->container->getProductService()->getStockBreakdownByProduct($product->id);

        // Last physical movement per warehouse (most recent stock_ledger entry)
        $lastMovements = [];
        foreach ($stockBreakdown['breakdown'] as $row) {
            $lastMovements[$row['warehouse_id']] = $this
                ->container
                ->getStockLedgerService()
                ->getLastMovement($product->id, $row['warehouse_id']);
        }

        return $this->view(self::TEMPLATE_DETAIL, [
            'product' => $product,
            'stockBreakdown' => $stockBreakdown,
            'lastMovements' => $lastMovements,
            'currentUserRole' => $currentUser->role->value,
        ]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('products.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'product' => null,
            'categories' => $this->container->getCategoryService()->listActiveCategories(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('products.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $imageUploadResult = $this->processImageUpload();
        if ($imageUploadResult->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'product' => null,
                'categories' => $this->container->getCategoryService()->listActiveCategories(),
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'errors' => [$this->t($imageUploadResult->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($imageUploadResult));
        }
        $imagePath = $imageUploadResult->data;
        $actorId = $this->currentUser()->id;

        $result = $this->container->getProductService()->createProduct($_POST, $imagePath, $actorId);

        if ($result->code !== Result::CODE_SUCCESS) {
            // The image was uploaded before validation ran; don't leave it orphaned.
            if ($imagePath !== null) {
                $this->container->getImageUploadService()->delete($imagePath);
            }

            $response = $this->view(self::TEMPLATE_FORM, [
                'product' => null,
                'categories' => $this->container->getCategoryService()->listActiveCategories(),
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        } else {
            // Optional opening stock: product_stocks row + Adjustment ledger entry
            // in one transaction (StockLedgerService::recordInitialStock).
            $initialWarehouseId = (int) ($_POST['initial_warehouse_id'] ?? 0);
            $initialQty = (int) ($_POST['initial_qty'] ?? 0);
            if ($initialWarehouseId > 0 && $initialQty > 0) {
                $stockResult = $this->container->getStockLedgerService()->recordInitialStock($result->data->id, $initialWarehouseId, $initialQty, $actorId);
                if ($stockResult->code !== Result::CODE_SUCCESS) {
                    $this->getSessionManager()->set(self::FLASH_ERROR, 'The product was created, but its initial stock could not be recorded. Receive it through a purchase order instead.');
                }
            }

            $response = $this->redirect(self::ROUTE_PRODUCTS);
        }

        return $response;
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('products.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        $product = null;
        if ($id !== null) {
            $product = $this->container->getProductService()->findById($id);
        }

        if ($product === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_FORM, [
            'product' => $product,
            'categories' => $this->container->getCategoryService()->listActiveCategories(),
            'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('products.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        // Failed saves re-render as an EDIT of this product — passing null here turned
        // the form into "New Product" posting to /products, so resubmitting created a duplicate.
        // Both the image-upload step and the update step can fail; the first failing
        // Result is captured in $errorResult and the form is re-rendered once below.
        $imageUploadResult = $this->processImageUpload();
        $errorResult = null;
        if ($imageUploadResult->code !== Result::CODE_SUCCESS) {
            $errorResult = $imageUploadResult;
        } else {
            $imagePath = $imageUploadResult->data;

            // Keep a reference to the old image path in case we need to delete it
            $oldImagePath = null;
            if ($imagePath !== null) {
                $product = $this->container->getProductService()->findById($id);
                $oldImagePath = $product?->imagePath;
            }

            $result = $this->container->getProductService()->updateProduct($id, $_POST, $this->currentUser()->id);

            if ($result->code !== Result::CODE_SUCCESS) {
                // The new image was uploaded before validation ran; don't leave it orphaned.
                if ($imagePath !== null) {
                    $this->container->getImageUploadService()->delete($imagePath);
                }

                $errorResult = $result;
            } elseif ($imagePath !== null) {
                $this->container->getProductService()->updateImagePath($id, $imagePath);
                if ($oldImagePath !== null) {
                    $this->container->getImageUploadService()->delete($oldImagePath);
                }
            }
        }

        if ($errorResult !== null) {
            $response = $this->view(self::TEMPLATE_FORM, [
                'product' => $this->container->getProductService()->findById($id),
                'categories' => $this->container->getCategoryService()->listActiveCategories(),
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'errors' => [$this->t($errorResult->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($errorResult));
        } else {
            $response = $this->redirect(self::ROUTE_PRODUCTS);
        }

        return $response;
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('products.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        $ok = false;
        if ($id !== null) {
            $result = $this->container->getProductService()->setActive($id, false, $this->currentUser()->id);
            $ok = $result->code === Result::CODE_SUCCESS;
        }

        if (!$ok) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_PRODUCTS);
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('products.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        $ok = false;
        if ($id !== null) {
            $result = $this->container->getProductService()->setActive($id, true, $this->currentUser()->id);
            $ok = $result->code === Result::CODE_SUCCESS;
        }

        if (!$ok) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_PRODUCTS);
    }

    private function categoryFilter()
    {
        $raw = $this->requestParam('category');
        if ($raw === null) { return null; }
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $id = filter_var($v, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) { $ids[] = $id; }
            }
        } else {
            $id = filter_var((string) $raw, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) { $ids[] = $id; }
        }
        return !empty($ids) ? $ids : null;
    }

    private function warehouseFilter()
    {
        $raw = $this->requestParam('warehouse_id');
        if ($raw === null) { return null; }
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $id = filter_var($v, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) { $ids[] = $id; }
            }
        } else {
            $id = filter_var((string) $raw, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) { $ids[] = $id; }
        }
        return !empty($ids) ? $ids : null;
    }

    private function stockStatusFilter(): ?string
    {
        $allowed = ['in_stock', 'low_stock', 'out_of_stock', 'inactive'];
        $val = strtolower(trim((string) ($this->requestParam('stock_status') ?? '')));

        return in_array($val, $allowed, true) ? $val : null;
    }

    // Returns a Result whose ->data is the uploaded path (string) or null when
    // no file was submitted — never echoes/exits directly. storeAction() and
    // updateAction() re-render the same product form with the error in
    // 'errors', exactly like every other validation failure on this page,
    // instead of a bare JSON response replacing the whole page (see AGENT.md
    // note on always returning a renderable response from a non-AJAX form).
    private function processImageUpload(): Result
    {
        $result = new Result();
        $uploadedFilename = (string) ($_FILES['image']['name'] ?? '');

        if ($uploadedFilename === '') {
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'No image submitted';
            $result->data = null;

            return $result;
        }

        $uploadResult = $this->container->getImageUploadService()->process($_FILES['image']);

        if ($uploadResult->code !== Result::CODE_SUCCESS) {
            $result->code = Result::CODE_VALIDATION;
            $result->info = $uploadResult->info;
            $result->data = null;

            return $result;
        }

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Image uploaded';
        $result->data = $uploadResult->data->path;

        return $result;
    }
}
