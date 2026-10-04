<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\EventLog;
use App\Repository\Interface\EventLogRepositoryInterface;

class EventLogMySQLRepository implements EventLogRepositoryInterface
{
    private $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function create($data)
    {
        $result = new Result();

        try {
            $metadata = $data['metadata'] ?? null;

            $result->data = $this->queryBuilder->insert('event_logs', [
                'user_id' => $data['user_id'] ?? null,
                'action' => (string) $data['action'],
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'description' => (string) $data['description'],
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'metadata' => $metadata !== null ? json_encode($metadata) : null,
            ]);

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to create event log';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Read side (activity feed / audit evidence) — not on any route yet,
    // provided so the table isn't write-only once EventLogService is wired.
    public function findAll($filters = [], $limit = 0, $offset = 0)
    {
        $result = new Result();

        try {
            $equalityFilters = $this->equalityFilters($filters);

            $rows = $this->queryBuilder->findAll(
                'event_logs',
                'el',
                'el.*, u.name AS user_name',
                [['type' => 'LEFT', 'table' => 'users', 'alias' => 'u', 'on' => 'u.id = el.user_id']],
                [],
                null,
                $equalityFilters,
                'el.created_at DESC, el.id DESC',
                $limit,
                $offset
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to list event logs';
            $result->data = array_map(function ($row) { return EventLog::fromArray($row); }, $rows);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function countAll($filters = [])
    {
        $result = new Result();

        try {
            $count = $this->queryBuilder->countAll(
                'event_logs',
                'el',
                [],
                [],
                null,
                $this->equalityFilters($filters)
            );

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to count event logs';
            $result->data = (int) $count;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    private function equalityFilters($filters)
    {
        $map = [
            'user_id' => 'el.user_id',
            'action' => 'el.action',
            'entity_type' => 'el.entity_type',
            'entity_id' => 'el.entity_id',
        ];

        $equalityFilters = [];
        foreach ($map as $key => $column) {
            if (($filters[$key] ?? null) !== null && $filters[$key] !== '') {
                $equalityFilters[$column] = $filters[$key];
            }
        }

        return $equalityFilters;
    }
}
