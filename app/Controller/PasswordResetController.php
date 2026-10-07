<?php

namespace App\Controller;

use App\Core\Result;

// Public (unauthenticated) forgot-password pages. Every POST is CSRF-protected.
class PasswordResetController extends BaseController
{
    public const ROUTE_LOGIN = '/login';
    public const ROUTE_DASHBOARD = '/dashboard';

    public const TEMPLATE_FORGOT = 'auth/forgot-password';
    public const TEMPLATE_RESET = 'auth/reset-password';

    public const FLASH_PASSWORD_RESET = 'flash.auth.password_reset';

    public function showForgotAction()
    {
        if ($this->currentUser() !== null) {
            return $this->redirect(self::ROUTE_DASHBOARD);
        }

        return $this->view(self::TEMPLATE_FORGOT, ['message' => null, 'error' => null, 'email' => '']);
    }

    public function requestAction()
    {
        $csrfError = $this->requireCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $email = trim((string) ($_POST['email'] ?? ''));

        // Same response whether or not the address is registered (the service never says).
        $result = $this->container->getPasswordResetService()->requestReset($email, $this->clientIp());

        return $this->view(self::TEMPLATE_FORGOT, ['message' => $result->info, 'error' => null, 'email' => '']);
    }

    public function showResetAction()
    {
        if ($this->currentUser() !== null) {
            return $this->redirect(self::ROUTE_DASHBOARD);
        }

        // The token is in the URL fragment (never sent to the server); the page script copies it into the form.
        return $this->view(self::TEMPLATE_RESET, ['error' => null, 'token' => '']);
    }

    public function resetAction()
    {
        $csrfError = $this->requireCsrf();
        if ($csrfError !== null) {
            return $csrfError;
        }

        $token = (string) ($_POST['token'] ?? '');

        $result = $this->container->getPasswordResetService()->resetPassword(
            $token,
            $_POST['password'] ?? '',
            $_POST['password_confirmation'] ?? '',
        );

        if ($result->code !== Result::CODE_SUCCESS) {
            // Echo the token back (only if it is well-formed) so a typo in the password does not strand the user.
            $keep = preg_match('/^[0-9a-f]{64}$/', $token) === 1 ? $token : '';

            return $this->view(self::TEMPLATE_RESET, ['error' => $result->info, 'token' => $keep])
                ->setStatusCode($this->formErrorStatus($result));
        }

        $this->getSessionManager()->set(self::FLASH_PASSWORD_RESET, true);

        return $this->redirect(self::ROUTE_LOGIN);
    }

    // Behind the Caddy proxy REMOTE_ADDR is the proxy's (private Docker) address, so the forwarded client address
    // is trusted only when the direct peer is private/loopback — a direct internet client cannot spoof it.
    private function clientIp()
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

        $isPrivatePeer = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($isPrivatePeer && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            $candidate = end($parts);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remote;
    }
}
