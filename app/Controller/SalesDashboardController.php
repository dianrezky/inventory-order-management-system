<?php

namespace App\Controller;

use App\Entity\Role;

class SalesDashboardController extends BaseController
{
    public const ROUTE_SALES_DASHBOARD = '/sales-dashboard';
    public const TEMPLATE = 'sales-dashboard/index';

    public function indexAction()
    {
        // Sales revenue/order figures belong to Admin and Sales only; WarehouseStaff's dashboard is stock & fulfillment (role matrix)
        $authError = $this->requirePermission('sales_orders.menu');
        if ($authError !== null) {
            return $authError;
        }

        $user = $this->currentUser();
        $period = $this->resolvePeriod();

        $service = $this->container->getSalesDashboardService();

        $userId = null;
        // Sales staff only see their own orders; Admin sees all
        if ($user->role === Role::Sales) {
            $userId = $user->id;
        }

        $stats = $service->getStats($userId, $period);

        return $this->view(self::TEMPLATE, [
            'user' => $user,
            'stats' => $stats,
            'period' => $period,
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    private function resolvePeriod(): string
    {
        $allowed = ['today', 'week', 'month', 'all'];
        $val = strtolower(trim((string) $this->requestParam('period', 'month')));

        return in_array($val, $allowed, true) ? $val : 'month';
    }
}
