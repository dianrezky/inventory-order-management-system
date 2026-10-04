<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\Interface\UserRepositoryInterface;
use DateTimeImmutable;

// In-memory fake backing the unit test suite (ADR-001 §3); no database dependency.
class UserFakeRepository implements UserRepositoryInterface
{
    private $usersById = [];

    public function __construct($users = [])
    {
        foreach ($users as $user) {
            $this->usersById[$user->id] = $user;
        }
    }

    public function add($user)
    {
        $this->usersById[$user->id] = $user;
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find user';
        $result->data = $this->usersById[$id] ?? null;

        return $result;
    }

    public function findByEmail($email)
    {
        $found = null;
        foreach ($this->usersById as $user) {
            if (strcasecmp($user->email, $email) === 0) {
                $found = $user;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find user';
        $result->data = $found;

        return $result;
    }

    public function findAll($search = null, $isActive = null, $role = null, $name = null, $email = null)
    {
        $all = array_values($this->usersById);

        // Discrete Name / Email fields (own field per column, ANDed) take
        // precedence over the combined $search (OR across both columns) —
        // same precedence rule as UserMySQLRepository::findAll().
        $hasDiscreteFilter = ($name !== null && $name !== '') || ($email !== null && $email !== '');
        if ($hasDiscreteFilter) {
            if ($name !== null && $name !== '') {
                $all = array_values(array_filter($all, function ($u) use ($name) {
                    return stripos($u->name, $name) !== false;
                }));
            }
            if ($email !== null && $email !== '') {
                $all = array_values(array_filter($all, function ($u) use ($email) {
                    return stripos($u->email, $email) !== false;
                }));
            }
        } elseif ($search !== null && $search !== '') {
            $all = array_values(array_filter(
                $all,
                function ($u) use ($search) {
                    return stripos($u->name, $search) !== false || stripos($u->email, $search) !== false;
                }
            ));
        }

        if ($isActive !== null && count($isActive) > 0) {
            $isActiveValues = [];
            foreach ($isActive as $s) {
                if ($s === 'active') { $isActiveValues[] = true; }
                elseif ($s === 'inactive') { $isActiveValues[] = false; }
            }
            if (!empty($isActiveValues)) {
                $all = array_values(array_filter($all, function ($u) use ($isActiveValues) {
                    return in_array($u->isActive, $isActiveValues, true);
                }));
            }
        }
        if ($role !== null && count($role) > 0) {
            $all = array_values(array_filter($all, function ($u) use ($role) {
                return in_array($u->role->value, $role, true);
            }));
        }

        usort($all, function ($a, $b) { return $a->name <=> $b->name; });

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list users';
        $result->data = $all;

        return $result;
    }

    public function emailExists($email, $excludeId = null)
    {
        $exists = false;
        foreach ($this->usersById as $user) {
            if ($excludeId !== null && $user->id === $excludeId) {
                continue;
            }
            if (strcasecmp($user->email, $email) === 0) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check email existence';
        $result->data = $exists;

        return $result;
    }

    public function create($data)
    {
        $id = count($this->usersById) === 0 ? 1 : max(array_keys($this->usersById)) + 1;

        $roleStr = (string) ($data['role'] ?? 'Admin');
        // Fail closed on an unrecognized role string, mirroring User::fromArray().
        $roleEnum = Role::tryFrom($roleStr) ?? Role::WarehouseStaff;

        $this->usersById[$id] = new User(
            $id,
            (string) $data['name'],
            (string) $data['email'],
            (string) $data['password_hash'],
            $roleEnum,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create user';
        $result->data = $id;

        return $result;
    }

    public function update($id, $data)
    {
        $existing = $this->usersById[$id] ?? null;

        $result = new Result();

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $roleStr = (string) ($data['role'] ?? '');
        $roleEnum = $roleStr !== '' ? (Role::tryFrom($roleStr) ?? $existing->role) : $existing->role;

        $this->usersById[$id] = new User(
            $existing->id,
            (string) $data['name'],
            (string) $data['email'],
            $existing->passwordHash,
            $roleEnum,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable(),
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update user';
        $result->data = null;

        return $result;
    }

    public function updatePassword($id, $passwordHash)
    {
        $existing = $this->usersById[$id] ?? null;

        $result = new Result();

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->usersById[$id] = new User(
            $existing->id,
            $existing->name,
            $existing->email,
            $passwordHash,
            $existing->role,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable(),
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update user password';
        $result->data = null;

        return $result;
    }

    public function setActive($id, $active)
    {
        $existing = $this->usersById[$id] ?? null;

        $result = new Result();

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->usersById[$id] = new User(
            $existing->id,
            $existing->name,
            $existing->email,
            $existing->passwordHash,
            $existing->role,
            $active,
            $existing->createdAt,
            new DateTimeImmutable(),
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update user status';
        $result->data = null;

        return $result;
    }
}
