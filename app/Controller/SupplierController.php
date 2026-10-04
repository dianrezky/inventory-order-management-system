<?php

namespace App\Controller;

use App\Core\Result;

class SupplierController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_SUPPLIERS = '/suppliers';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/suppliers/list';
    public const TEMPLATE_DETAIL = 'master/suppliers/detail';
    public const TEMPLATE_FORM = 'master/suppliers/form';

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $name = $this->textFilter('name');
        $contactPerson = $this->textFilter('contact_person');
        $email = $this->textFilter('email');
        $statuses = $this->statusFilter();
        $suppliers = $this->container->getSupplierService()->listSuppliers(null, $statuses, $name, $contactPerson, $email);

        return $this->view(self::TEMPLATE_LIST, [
            'suppliers' => $suppliers,
            'filterName' => $name,
            'contactPerson' => $contactPerson,
            'email' => $email,
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
        $user = $this->currentUser();
        $supplier = null;
        if ($id !== null) {
            $supplier = $this->container->getSupplierService()->findById($id);
        }

        if ($supplier === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_DETAIL, [
            'supplier' => $supplier,
            'currentUserRole' => $user->role->value,
        ]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('suppliers.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'supplier' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('suppliers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $result = $this->container->getSupplierService()->createSupplier($_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'supplier' => null,
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_SUPPLIERS);
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('suppliers.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        $supplier = null;
        if ($id !== null) {
            $supplier = $this->container->getSupplierService()->findById($id);
        }

        if ($supplier === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_FORM, [
            'supplier' => $supplier,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('suppliers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getSupplierService()->updateSupplier($id, $_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            // Re-render as an EDIT of this record — passing null here turned the
            // form into a create form, so resubmitting created a duplicate.
            $response = $this->view(self::TEMPLATE_FORM, [
                'supplier' => $this->container->getSupplierService()->findById($id),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        } else {
            $response = $this->redirect(self::ROUTE_SUPPLIERS);
        }

        return $response;
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('suppliers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        $ok = false;
        if ($id !== null) {
            $result = $this->container->getSupplierService()->setActive($id, false, $this->currentUser()->id);
            $ok = $result->code === Result::CODE_SUCCESS;
        }

        if (!$ok) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_SUPPLIERS);
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('suppliers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        $ok = false;
        if ($id !== null) {
            $result = $this->container->getSupplierService()->setActive($id, true, $this->currentUser()->id);
            $ok = $result->code === Result::CODE_SUCCESS;
        }

        if (!$ok) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_SUPPLIERS);
    }
}
