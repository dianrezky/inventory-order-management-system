<?php

namespace App\Controller;

use App\Core\Result;

class ProfileController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_PROFILE = '/my-profile';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_PROFILE = 'account/profile';

    // ================================================================
    // FLASH CONSTANTS
    // ================================================================
    public const FLASH_UPDATED = 'flash.profile.updated';

    public function showAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();

        return $this->view(self::TEMPLATE_PROFILE, [
            'profileUser' => $user,
            'profileErrors' => [],
            // profile.php's $val closure captures $profileOld with `use` — it must always be defined.
            'profileOld' => [],
            'profileUpdated' => $this->pullFlash(self::FLASH_UPDATED) === true,
        ]);
    }

    public function updateAction()
    {
        $guardError = $this->requireAuthWithCsrf();
        if ($guardError !== null) {
            return $guardError;
        }

        $user = $this->currentUser();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        $result = $this->container->getUserService()->updateProfile($user->id, $name, $email);

        if ($result->code !== Result::CODE_SUCCESS) {
            return $this->view(self::TEMPLATE_PROFILE, [
                'profileUser' => $user,
                'profileErrors' => [$this->t($result->info)],
                'profileOld' => $_POST,
            ])->setStatusCode($this->formErrorStatus($result));
        }

        $this->getSessionManager()->set(self::FLASH_UPDATED, true);

        return $this->redirect(self::ROUTE_PROFILE);
    }
}
