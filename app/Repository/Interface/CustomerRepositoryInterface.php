<?php

namespace App\Repository\Interface;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface CustomerRepositoryInterface
{
    public function findById($id);

     public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $email = null, $phone = null, $contactPerson = null);

    public function create($data);

    public function update($id, $data);

    public function setActive($id, $active);

    public function delete($id);

    public function findAllActive();

    public function search($search, $sortBy, $sortDir, $filter, $page, $perPage);

    public function countSearch($search, $filter);
}
