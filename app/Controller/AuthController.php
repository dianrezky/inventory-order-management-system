<?php

namespace App\Controller;

use App\Core\Result;

class AuthController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_LOGIN = '/login';
    public const ROUTE_LOGOUT = '/logout';
    public const ROUTE_DASHBOARD = '/dashboard';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_LOGIN = 'auth/login';

    // ================================================================
    // MESSAGE CONSTANTS
    // ================================================================
    public const MESSAGE_INVALID_CREDENTIALS = 'The email address or password you entered is incorrect.';
    public const MESSAGE_SESSION_EXPIRED = 'Your session has expired. Please log in again.';

    public function showLoginAction()
    {
        if ($this->currentUser() !== null) {
            return $this->redirect(self::ROUTE_DASHBOARD);
        }

        return $this->view(self::TEMPLATE_LOGIN, [
            'error' => null,
            'email' => '',
            'notice' => $this->pullFlash(PasswordResetController::FLASH_PASSWORD_RESET) === true
                ? 'Your password has been changed. Please sign in with your new password.'
                : null,
        ]);
    }

    public function loginAction()
    {
        $csrfError = $this->requireCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = new Result();
        if ($email === '' || $password === '') {
            $result->code = Result::CODE_VALIDATION;
        } else {
            $result = $this->container->getAuthService()->login($email, $password);
        }

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_LOGIN, [
                'error' => self::MESSAGE_INVALID_CREDENTIALS,
                'email' => $email,
            ]);
        }

        return $this->redirect(self::ROUTE_DASHBOARD);
    }

    public function logoutAction()
    {
        $csrfError = $this->requireCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $this->container->getAuthService()->logout();

        return $this->redirect(self::ROUTE_LOGIN);
    }
}
