<?php

namespace App\Controller;

use App\Core\Result;

class UserController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_USERS = '/users';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LIST = 'master/users/list';
    public const TEMPLATE_DETAIL = 'master/users/detail';
    public const TEMPLATE_FORM = 'master/users/form';

    public function indexAction()
    {
        $authError = $this->requirePermission('users.manage');
        if ($authError !== null) {
            return $authError;
        }

        $name = $this->textFilter('name');
        $email = $this->textFilter('email');
        $statuses = $this->statusFilter();
        $roles = $this->roleFilter();
        $users = $this->container->getUserService()->listUsers(null, $statuses, $roles, $name, $email);

        return $this->view(self::TEMPLATE_LIST, [
            'users' => $users,
            'currentUserId' => $this->currentUser()->id,
            'filterName' => $name,
            'email' => $email,
            'statuses' => $statuses,
            'roles' => $roles,
        ]);
    }

    public function showAction($id)
    {
        $authError = $this->requirePermission('users.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        $user = null;
        if ($id !== null) {
            $user = $this->container->getUserService()->findById($id);
        }

        if ($user === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_DETAIL, ['user' => $user]);
    }

    public function createFormAction()
    {
        $authError = $this->requirePermission('users.manage');
        if ($authError !== null) {
            return $authError;
        }

        return $this->view(self::TEMPLATE_FORM, [
            'user' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function storeAction()
    {
        $guardError = $this->requirePermissionWithCsrf('users.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $result = $this->container->getUserService()->createUser($_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_FORM, [
                'user' => null,
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        return $this->redirect(self::ROUTE_USERS);
    }

    public function editFormAction($id)
    {
        $authError = $this->requirePermission('users.manage');
        if ($authError !== null) {
            return $authError;
        }

        $id = $this->decodeId($id);
        $user = null;
        if ($id !== null) {
            $user = $this->container->getUserService()->findById($id);
        }

        if ($user === null) {
            return $this->notFound();
        }

        return $this->view(self::TEMPLATE_FORM, [
            'user' => $user,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function updateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('users.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getUserService()->updateUser($id, $_POST, $this->currentUser()->id);

        if ($result->code !== Result::CODE_SUCCESS) {
            // Re-render as an EDIT of this record — passing null here turned the
            // form into "New User" posting to /users, so resubmitting created a duplicate.
            $response = $this->view(self::TEMPLATE_FORM, [
                'user' => $this->container->getUserService()->findById($id),
                'errors' => [$this->t($result->info)],
                'old' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        } else {
            $response = $this->redirect(self::ROUTE_USERS);
        }

        return $response;
    }

    public function deactivateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('users.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        if ($id === null) {
            return $this->notFound();
        }

        $result = $this->container->getUserService()->setActive($id, false, $this->currentUser()->id);

        if ($result->code === Result::CODE_VALIDATION) {
            // A refused self-deactivation is a rule violation, not a missing record
            $response = $this->badRequest($this->t($result->info));
        } elseif ($result->code !== Result::CODE_SUCCESS) {
            $response = $this->notFound();
        } else {
            $response = $this->redirect(self::ROUTE_USERS);
        }

        return $response;
    }

    public function activateAction($id)
    {
        $guardError = $this->requirePermissionWithCsrf('users.manage');
        if ($guardError !== null) {
            return $guardError;
        }

        $id = $this->decodeId($id);
        $ok = false;
        if ($id !== null) {
            $result = $this->container->getUserService()->setActive($id, true, $this->currentUser()->id);
            $ok = $result->code === Result::CODE_SUCCESS;
        }

        if (!$ok) {
            return $this->notFound();
        }

        return $this->redirect(self::ROUTE_USERS);
    }
}
