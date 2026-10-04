<?php

namespace App\Repository\Interface;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface NotificationRepositoryInterface
{
    public function create($data);

    public function existsUnreadFor($productId, $warehouseId, $type);

    public function findUnread($limit = 10);

    public function countUnread();

    public function markAllRead();
}
