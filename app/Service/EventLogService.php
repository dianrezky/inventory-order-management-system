<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\EventLogRepositoryInterface;

// Central place every other Service calls into to record a CUD action —
// "the utility" for event logging (one shared home, not duplicated per Service).
//
// event_logs is NEVER a source of truth (see database/schema.sql "16. event_logs"
// header) and is NEVER allowed to break a real business transaction: record()
// swallows every failure internally (logged via error_log(), never thrown) so a
// logging problem can never roll back or fail a PO/SO/master-data write. This
// mirrors the read paths in this codebase that already tolerate a missing
// infrastructure piece (CacheService degrading to no-op, SessionManager falling
// back to file sessions) rather than letting it become a critical-failure vector.
class EventLogService
{
    private $eventLogRepository;

    public function __construct(EventLogRepositoryInterface $eventLogRepository)
    {
        $this->eventLogRepository = $eventLogRepository;
    }

    // $userId is the acting user's id, or null for a system/unauthenticated
    // actor (e.g. a failed login attempt, or the scheduled job).
    // $entityType/$entityId identify the affected record; both null for
    // non-entity events (login/logout). $metadata is optional structured
    // context (e.g. changed fields) — informational only, never parsed back.
    public function record($userId, $action, $entityType, $entityId, $description, $metadata = null)
    {
        try {
            $createResult = $this->eventLogRepository->create([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'ip_address' => $this->currentIp(),
                'user_agent' => $this->currentUserAgent(),
                'metadata' => $metadata,
            ]);

            if ($createResult->code !== Result::CODE_SUCCESS) {
                error_log('EventLogService::record failed to persist: ' . $createResult->info);
            }
        } catch (\Throwable $e) {
            // Never let a logging failure propagate into the caller's transaction.
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
        }
    }

    public function listRecent($filters = [], $limit = 20, $offset = 0)
    {
        $findResult = $this->eventLogRepository->findAll($filters, $limit, $offset);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countAll($filters = [])
    {
        $countResult = $this->eventLogRepository->countAll($filters);

        return $countResult->code === Result::CODE_SUCCESS ? $countResult->data : 0;
    }

    private function currentIp()
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function currentUserAgent()
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // VARCHAR(255) in the schema — truncate defensively rather than let
        // a pathological header value fail the INSERT.
        if ($ua !== null && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }

        return $ua;
    }
}
