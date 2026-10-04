<?php

namespace App\Repository\Interface;

use App\Entity\User;

// Services depend on this interface, never on a concrete implementation (DIP — AGENT.md §9.3).
interface UserRepositoryInterface
{
    public function findById($id);

    public function findByEmail($email);

    public function findAll($search = null, $isActive = null, $role = null, $name = null, $email = null);

    public function emailExists($email, $excludeId = null);

    public function create($data);

    public function update($id, $data);

    public function updatePassword($id, $passwordHash);

    public function setActive($id, $active);
}
