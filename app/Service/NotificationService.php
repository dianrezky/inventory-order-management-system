<?php

namespace App\Service;

use App\Core\Result;
use App\Entity\Notification;
use App\Repository\Interface\NotificationRepositoryInterface;

class NotificationService
{
    private $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    // Called from scripts/check-low-stock.php (JOB-01) for every product whose
    // total stock is below reorder point ($warehouseId is null for that
    // product-level alert). Deduplicated: skip if an unread low-stock
    // notification for the same product (+warehouse, when given) already exists,
    // so re-running the script while stock stays low doesn't spam the dashboard.
    public function notifyLowStock($productId, $warehouseId, $message)
    {
        $existsResult = $this->notificationRepository->existsUnreadFor(
            $productId,
            $warehouseId,
            Notification::TYPE_LOW_STOCK
        );

        if ($existsResult->code !== Result::CODE_SUCCESS) {
            return $existsResult;
        }

        if ($existsResult->data === true) {
            $result = new Result();
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'An unread notification already exists for this product/warehouse.';
            $result->data = null;

            return $result;
        }

        return $this->notificationRepository->create([
            'type' => Notification::TYPE_LOW_STOCK,
            'message' => $message,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);
    }

    // Admin and WarehouseStaff dashboards both call this — recipients are fixed
    // to those two roles (per the brief's low-stock visibility matrix), not
    // configurable per notification.
    public function getUnreadForDashboard($limit = 10)
    {
        $findResult = $this->notificationRepository->findUnread($limit);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countUnread()
    {
        $countResult = $this->notificationRepository->countUnread();

        return $countResult->code === Result::CODE_SUCCESS ? $countResult->data : 0;
    }

    public function markAllRead()
    {
        return $this->notificationRepository->markAllRead();
    }
}
