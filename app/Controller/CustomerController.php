<?php

namespace App\Controller;

use App\Core\Result;

class CustomerController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_CUSTOMERS = '/customers';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/customers/list';
    public const TEMPLATE_DETAIL = 'master/customers/detail';
    public const TEMPLATE_FORM = 'master/customers/form';

    public function indexAction()
    {
        // PROJECT_REFERENCE.md §1.2: WarehouseStaff's master-data visibility
        // is scoped to products & stock only, narrower than Sales's "view
        // catalog" — so this is customers.view, not just requireAuth().
        $authError = $this->requirePermission('customers.view');
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $name = $this->textFilter('name');
        $email = $this->textFilter('email');
        $phone = $this->textFilter('phone');
        $contactPerson = $this->textFilter('contact_person');
        $statuses = $this->statusFilter();
        $customers = $this->container->getCustomerService()->listCustomers(null, $statuses, $name, $email, $phone, $contactPerson);

        return $this->view(self::TEMPLATE_LIST, [
            'customers' => $customers,
            'filterName' => $name,
            'email' => $email,
            'phone' => $phone,
            'contactPerson' => $contactPerson,
            'statuses' => $statuses,
            'currentUserRole' => $user->role->value,
        ]);
    }

    public function showAction($id)
    {
        $authError = $this->requirePermission('customers.view');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $user = $this->currentUser();
        $customer = $this->container->getCustomerService()->findById($id);

        if ($customer === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_DETAIL, [
            'customer' => $customer,
            'currentUserRole' => $user->role->value,
        ]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('customers.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'customer' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('customers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $result = $this->container->getCustomerService()->createCustomer($_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'customer' => null,
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_CUSTOMERS);
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('customers.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $customer = $this->container->getCustomerService()->findById($id);

        if ($customer === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_FORM, [
            'customer' => $customer,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('customers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCustomerService()->updateCustomer($id, $_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            // Re-render as an EDIT of this record — passing null here turned the
            // form into a create form, so resubmitting created a duplicate.
            return $this->view(self::TEMPLATE_FORM, [
                'customer' => $this->container->getCustomerService()->findById($id),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_CUSTOMERS);
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('customers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCustomerService()->setActive($id, false, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_CUSTOMERS);
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('customers.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getCustomerService()->setActive($id, true, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_CUSTOMERS);
    }
}
