<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\EventLog;
use App\Repository\Interface\EventLogRepositoryInterface;
use DateTimeImmutable;

class EventLogFakeRepository implements EventLogRepositoryInterface
{
    private $logs = [];
    private $nextId = 1;

    public function __construct($logs = [])
    {
        foreach ($logs as $log) {
            $this->logs[] = $log;
            $this->nextId = max($this->nextId, $log->id + 1);
        }
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->logs[] = new EventLog(
            $id,
            $data['user_id'] ?? null,
            (string) $data['action'],
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null,
            (string) $data['description'],
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $data['metadata'] ?? null,
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create event log';
        $result->data = $id;

        return $result;
    }

    public function findAll($filters = [], $limit = 0, $offset = 0)
    {
        $rows = array_values(array_filter($this->logs, function ($log) use ($filters) {
            foreach (['user_id' => 'userId', 'action' => 'action', 'entity_type' => 'entityType', 'entity_id' => 'entityId'] as $key => $prop) {
                if (($filters[$key] ?? null) !== null && $filters[$key] !== '' && $log->$prop != $filters[$key]) {
                    return false;
                }
            }

            return true;
        }));

        usort($rows, function ($a, $b) { return $b->id <=> $a->id; });

        if ($limit > 0) {
            $rows = array_slice($rows, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list event logs';
        $result->data = $rows;

        return $result;
    }

    public function countAll($filters = [])
    {
        $findResult = $this->findAll($filters);

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count event logs';
        $result->data = count($findResult->data);

        return $result;
    }
}
