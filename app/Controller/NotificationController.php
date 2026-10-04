<?php

namespace App\Controller;

use App\Entity\Role;

class NotificationController extends BaseController
{
    public const ROUTE_MARK_ALL_READ = '/notifications/mark-all-read';

    public const MESSAGE_FORBIDDEN = "You don't have permission to manage notifications.";

    public function markAllReadAction()
    {
        $guardError = $this->requireAuthWithCsrf();
        if ($guardError !== null) {
            return $guardError;
        }

        // Same visibility rule as the notification bell/panel themselves
        // (BaseController::view(), DashboardController): only Admin and
        // WarehouseStaff can see unread notifications, so only they may clear
        // them. UI hiding the bell for Sales is not authorization on its own
        // (BR-017) — this is what actually enforces it server side.
        $user = $this->currentUser();
        if ($user->role !== Role::Admin && $user->role !== Role::WarehouseStaff) {
            return $this->forbidden(self::MESSAGE_FORBIDDEN);
        }

        $this->container->getNotificationService()->markAllRead();

        // Return to wherever the bell was opened from (dashboard, products
        // page, etc.) instead of always bouncing to the dashboard — the bell
        // is now shown in the header on every page. Only the path+query from
        // the referrer is trusted, never its host, so this can't be turned
        // into an open redirect.
        return $this->redirect($this->safeRedirectBackPath());
    }

    private function safeRedirectBackPath()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = parse_url($referer, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return '/dashboard';
        }

        // Some pages are rendered by POST-only routes: list filters/pagination
        // (/x/search, /warehouses/{id}/stock) and a failed edit re-rendered at
        // /x/{id}/update. A GET redirect back to those has no route (404), so land
        // on the GET page they belong to. The query is never carried over.
        $path = preg_replace('#/update$#', '/edit', $path);

        return preg_replace('#/(search|stock)$#', '', $path);
    }
}
