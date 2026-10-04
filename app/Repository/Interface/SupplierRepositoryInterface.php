<?php

namespace App\Repository\Interface;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface SupplierRepositoryInterface
{
    public function findById($id);

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $contactPerson = null, $email = null);

    public function create($data);

    public function update($id, $data);

    public function setActive($id, $active);

    public function delete($id);

    public function findAllActive();
}
