<?php

namespace App\Repository\Interface;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
// event_logs is insert-only (like stock_ledger) — deliberately no update/delete method exists.
interface EventLogRepositoryInterface
{
    public function create($data);

    public function findAll($filters = [], $limit = 0, $offset = 0);

    public function countAll($filters = []);
}
