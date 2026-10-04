<?php

namespace App\Controller;

use App\Entity\Role;

class DashboardController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_DASHBOARD = '/dashboard';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_DASHBOARD = 'dashboard/index';

    // ================================================================
    // MESSAGE CONSTANTS
    // ================================================================
    public const MESSAGE_INTERNAL_ERROR = 'An internal error occurred. Please try again later.';

    public function indexAction()
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $dashboardService = $this->container->getDashboardService();

        if ($user->role === Role::Admin) {
            $stats = $dashboardService->getAdminStats();
        } elseif ($user->role === Role::Sales) {
            $stats = $dashboardService->getSalesStats($user->id);
        } elseif ($user->role === Role::WarehouseStaff) {
            $stats = $dashboardService->getWarehouseStats();
        } else {
            $stats = null;
        }

        if ($stats === null) {
            return $this->badRequest(self::MESSAGE_INTERNAL_ERROR);
        }

        // Notifications (bonus feature, §2 "Scheduled Job — Notifikasi stok
        // rendah") are only shown to Admin and WarehouseStaff, matching who
        // sees low-stock data elsewhere on their dashboards.
        $notifications = [];
        if ($user->role === Role::Admin || $user->role === Role::WarehouseStaff) {
            $notifications = $this->container->getNotificationService()->getUnreadForDashboard();
        }

        return $this->view(self::TEMPLATE_DASHBOARD, [
            'user' => $user,
            'stats' => $stats,
            'notifications' => $notifications,
            'csrfToken' => $this->csrfToken(),
        ]);
    }
}
