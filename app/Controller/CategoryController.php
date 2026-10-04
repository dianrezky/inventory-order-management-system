<?php

namespace App\Controller;

use App\Core\Result;
use App\Service\CategoryService;

class CategoryController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_CATEGORIES = '/categories';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/categories/list';
    public const TEMPLATE_DETAIL = 'master/categories/detail';

    // ================================================================
    // CONFIG CONSTANTS
    // ================================================================
    public const PER_PAGE_OPTIONS = [10, 25, 50];
    public const DEFAULT_PER_PAGE = 10;
    public const EXPORT_FILE = 'categories.csv';

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $categoryName = $this->textFilter('name');
        $categoryCode = $this->textFilter('code');
        $statuses = $this->statusFilter();
        $sort = $this->sortParam();
        $page = max(1, (int) ($this->requestParam('page') ?? 1));
        $perPage = $this->perPageParam();

        $metrics = $this->container->getCategoryService()->getMetrics();
        $paged = $this->container->getCategoryService()->listPaged(null, $statuses, $sort, $page, $perPage, $categoryName, $categoryCode);

        return $this->view(self::TEMPLATE_LIST, [
            'categories' => $paged['items'],
            'total' => $paged['total'],
            'page' => $page,
            'perPage' => $perPage,
            'metrics' => $metrics,
            'categoryName' => $categoryName,
            'categoryCode' => $categoryCode,
            'statuses' => $statuses,
            'sort' => $sort,
            'currentUserRole' => $user->role->value,
            'autoOpenAdd' => $this->pullFlash('flash.categories.open_add') === true,
            'autoEditCategory' => $this->autoEditCategory(),
        ]);
    }

    // The category that /categories/{id}/edit asked to open. Loaded directly
    // (not looked up among the current page's rows) so the modal also opens for
    // a category that isn't on page 1 of the default sort.
    private function autoEditCategory()
    {
        $categoryId = $this->pullFlash('flash.categories.open_edit');
        if ($categoryId === null) {
            return null;
        }

        $category = $this->container->getCategoryService()->findById((int) $categoryId);
        if ($category === null) {
            return null;
        }

        return [
            'id' => $this->encodeId($category->id),
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description ?? '',
            'status' => $category->isActive ? 'active' : 'inactive',
        ];
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
        $category = $this->container->getCategoryService()->findById($id);

        if ($category === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_DETAIL, [
            'category' => $category,
            'currentUserRole' => $user->role->value,
        ]);
    }

    // The Categories list page now creates/edits through the Add/Edit
    // Category modal (Fetch API, see categories.js) rather than a separate
    // page, but the route stays reachable — a bookmarked/shared link just
    // lands on the list with that record's edit modal opened.
    public function createFormAction()
    {
        $authError = $this->requirePermission('categories.manage');
        if ($authError !== null) {
            return $authError;
        }

        $this->getSessionManager()->set('flash.categories.open_add', true);

        return $this->redirect(self::ROUTE_CATEGORIES);
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('categories.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $this->getSessionManager()->set('flash.categories.open_edit', $id);

        return $this->redirect(self::ROUTE_CATEGORIES);
    }

    // Modal submit (Fetch API): always answers in JSON — the modal is the
    // only caller, so there's no server-rendered "re-show the form" path to
    // keep in sync here the way the old separate create/edit pages needed.
    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('categories.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $result = $this->container->getCategoryService()->createCategory($_POST, $this->currentUser()->id);

        return $this->categoryJsonResponse($result);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('categories.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCategoryService()->updateCategory($id, $_POST, $this->currentUser()->id);

        return $this->categoryJsonResponse($result);
    }

    public function deleteAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('categories.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCategoryService()->deleteCategory($id, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->json(['ok' => false, 'error' => $this->t($result->info)], $this->statusForResult($result));
        }

        return $this->json(['ok' => true, 'message' => $result->info]);
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('categories.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCategoryService()->setActive($id, false, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_CATEGORIES);
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('categories.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCategoryService()->setActive($id, true, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_CATEGORIES);
    }

    // Read-only — every authenticated role (including read-only Sales /
    // WarehouseStaff) can export what they can already see on the list.
    public function exportAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $categoryName = $this->textFilter('name');
        $categoryCode = $this->textFilter('code');
        $statuses = $this->statusFilter();
        $sort = $this->sortParam();

        $categories = $this->container->getCategoryService()->listAllFiltered(null, $statuses, $sort, $categoryName, $categoryCode);
        $rows = array_map(function ($c) {
            return [
                'code' => $c->code,
                'name' => $c->name,
                'description' => $c->description,
                'assigned_sku_count' => $c->assignedSkuCount,
                'is_active' => $c->isActive,
                'updated_at' => $c->updatedAt !== null ? $c->updatedAt->format('Y-m-d H:i:s') : '',
            ];
        }, $categories);

        $csv = $this->container->getCsvExportService()->exportCategories($rows);

        return $this->csv($csv, self::EXPORT_FILE);
    }

    private function categoryJsonResponse(Result $result)
    {
        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->json(['ok' => false, 'error' => $this->t($result->info)], $this->statusForResult($result));
        }

        $category = $result->data;

        return $this->json([
            'ok' => true,
            'message' => $result->info,
            'category' => [
                'id' => $this->encodeId($category->id),
                'code' => $category->code,
                'name' => $category->name,
                'description' => $category->description,
                'isActive' => $category->isActive,
                'assignedSkuCount' => $category->assignedSkuCount,
                'updatedAt' => $category->updatedAt !== null ? $category->updatedAt->format('d/m/Y H:i') : null,
            ],
        ]);
    }

    private function statusForResult(Result $result)
    {
        return $result->code === Result::CODE_VALIDATION ? 422 : 500;
    }

    private function sortParam()
    {
        $sort = (string) ($this->requestParam('sort') ?? '');

        return in_array($sort, CategoryService::VALID_SORTS, true) ? $sort : CategoryService::SORT_NAME_ASC;
    }

    private function perPageParam()
    {
        $perPage = (int) ($this->requestParam('per_page') ?? self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }
}
