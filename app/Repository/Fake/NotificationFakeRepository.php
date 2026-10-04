<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Notification;
use App\Repository\Interface\NotificationRepositoryInterface;
use DateTimeImmutable;

class NotificationFakeRepository implements NotificationRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($notifications = [])
    {
        foreach ($notifications as $notification) {
            $this->byId[$notification->id] = $notification;
            $this->nextId = max($this->nextId, $notification->id + 1);
        }
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Notification(
            $id,
            (string) $data['type'],
            (string) $data['message'],
            $data['product_id'] ?? null,
            $data['warehouse_id'] ?? null,
            $data['product_sku'] ?? null,
            $data['product_name'] ?? null,
            $data['warehouse_name'] ?? null,
            null,
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create notification';
        $result->data = $id;

        return $result;
    }

    public function existsUnreadFor($productId, $warehouseId, $type)
    {
        $exists = false;
        foreach ($this->byId as $n) {
            if ($n->type === $type && $n->productId === $productId && $n->warehouseId === $warehouseId && !$n->isRead()) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check unread notification';
        $result->data = $exists;

        return $result;
    }

    public function findUnread($limit = 10)
    {
        $unread = array_values(array_filter($this->byId, function ($n) { return !$n->isRead(); }));
        usort($unread, function ($a, $b) { return $b->createdAt <=> $a->createdAt; });

        if ($limit > 0) {
            $unread = array_slice($unread, 0, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list unread notifications';
        $result->data = $unread;

        return $result;
    }

    public function countUnread()
    {
        $count = count(array_filter($this->byId, function ($n) { return !$n->isRead(); }));

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count unread notifications';
        $result->data = $count;

        return $result;
    }

    public function markAllRead()
    {
        foreach ($this->byId as $id => $n) {
            if (!$n->isRead()) {
                $this->byId[$id] = new Notification(
                    $n->id,
                    $n->type,
                    $n->message,
                    $n->productId,
                    $n->warehouseId,
                    $n->productSku,
                    $n->productName,
                    $n->warehouseName,
                    new DateTimeImmutable(),
                    $n->createdAt
                );
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to mark notifications read';
        $result->data = null;

        return $result;
    }
}
