<?php

namespace App\Entity;

use DateTimeImmutable;

// General, insert-only activity/event log row. NOT the stock ledger — see
// database/schema.sql "16. event_logs" header comment for the boundary.
class EventLog
{
    public $id;
    public $userId;
    public $userName;   // populated from LEFT JOIN users u ON u.id = el.user_id
    public $action;
    public $entityType;
    public $entityId;
    public $description;
    public $ipAddress;
    public $userAgent;
    public $metadata;
    public $createdAt;

    public function __construct(
        $id,
        $userId,
        $userName,
        $action,
        $entityType,
        $entityId,
        $description,
        $ipAddress,
        $userAgent,
        $metadata,
        $createdAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->description = $description;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt;
    }

    public static function fromArray($row)
    {
        $metadata = null;
        if (isset($row['metadata']) && $row['metadata'] !== null && $row['metadata'] !== '') {
            $decoded = json_decode((string) $row['metadata'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $metadata = $decoded;
            }
        }

        return new self(
            (int) $row['id'],
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            $row['user_name'] ?? null,
            (string) $row['action'],
            $row['entity_type'] ?? null,
            isset($row['entity_id']) && $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            (string) $row['description'],
            $row['ip_address'] ?? null,
            $row['user_agent'] ?? null,
            $metadata,
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'description' => $this->description,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
