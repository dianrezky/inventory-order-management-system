<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\Notification;
use App\Repository\Interface\NotificationRepositoryInterface;

class NotificationMySQLRepository implements NotificationRepositoryInterface
{
    private $db;
    private $queryBuilder;

    public function __construct(Database $db, QueryBuilder $queryBuilder)
    {
        $this->db = $db;
        $this->queryBuilder = $queryBuilder;
    }

    public function create($data)
    {
        $result = new Result();

        try {
            $result->data = $this->queryBuilder->insert('notifications', [
                'type' => (string) $data['type'],
                'message' => (string) $data['message'],
                'product_id' => $data['product_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create notification';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function existsUnreadFor($productId, $warehouseId, $type)
    {
        $result = new Result();

        try {
            // A product-level low-stock notification has warehouse_id = NULL.
            // `warehouse_id = NULL` is never true in SQL, so dedup must use
            // `IS NULL` for that case or every cron run would create a duplicate.
            $params = ['type' => $type, 'product_id' => $productId];
            if ($warehouseId === null) {
                $sql = 'SELECT id FROM notifications '
                    . 'WHERE type = :type AND product_id = :product_id AND warehouse_id IS NULL '
                    . 'AND read_at IS NULL LIMIT 1';
            } else {
                $sql = 'SELECT id FROM notifications '
                    . 'WHERE type = :type AND product_id = :product_id AND warehouse_id = :warehouse_id '
                    . 'AND read_at IS NULL LIMIT 1';
                $params['warehouse_id'] = $warehouseId;
            }

            $stmt = $this->db->pdo()->prepare($sql);
            $stmt->execute($params);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to check unread notification';
            $result->data = $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findUnread($limit = 10)
    {
        $result = new Result();

        try {
            $sql = 'SELECT n.id, n.type, n.message, n.product_id, n.warehouse_id, n.read_at, n.created_at, '
                . 'p.sku AS product_sku, p.name AS product_name, w.name AS warehouse_name '
                . 'FROM notifications n '
                . 'LEFT JOIN products p ON p.id = n.product_id '
                . 'LEFT JOIN warehouses w ON w.id = n.warehouse_id '
                . 'WHERE n.read_at IS NULL '
                . 'ORDER BY n.created_at DESC '
                . 'LIMIT ' . (int) $limit;

            $stmt = $this->db->pdo()->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list unread notifications';
            $result->data = array_map(function ($row) { return Notification::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countUnread()
    {
        $result = new Result();

        try {
            $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL');
            $stmt->execute();

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count unread notifications';
            $result->data = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function markAllRead()
    {
        $result = new Result();

        try {
            $stmt = $this->db->pdo()->prepare('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
            $stmt->execute();

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to mark notifications read';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }
}
