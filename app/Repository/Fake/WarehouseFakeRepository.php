<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Warehouse;
use App\Repository\Interface\WarehouseRepositoryInterface;
use DateTimeImmutable;

class WarehouseFakeRepository implements WarehouseRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($warehouses = [])
    {
        foreach ($warehouses as $warehouse) {
            $this->byId[$warehouse->id] = $warehouse;
            $this->nextId = max($this->nextId, $warehouse->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find warehouse';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $code = null, $name = null, $location = null)
    {
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($w) use ($search) {
                return stripos($w->code, $search) !== false
                    || stripos($w->name, $search) !== false;
            }));
        }

        if ($code !== null && $code !== '') {
            $all = array_values(array_filter($all, function ($w) use ($code) {
                return stripos($w->code, $code) !== false;
            }));
        }

        if ($name !== null && $name !== '') {
            $all = array_values(array_filter($all, function ($w) use ($name) {
                return stripos($w->name, $name) !== false;
            }));
        }

        if ($location !== null && $location !== '') {
            $all = array_values(array_filter($all, function ($w) use ($location) {
                return $w->location !== null && stripos($w->location, $location) !== false;
            }));
        }

        if ($statuses !== null && count($statuses) > 0) {
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = true; }
                elseif ($s === 'inactive') { $isActiveValues[] = false; }
            }
            if (count($isActiveValues) > 0) {
                $all = array_values(array_filter($all, function ($w) use ($isActiveValues) {
                    return in_array($w->isActive, $isActiveValues, true);
                }));
            }
        }

        usort($all, function ($a, $b) { return $a->name <=> $b->name; });

        if ($limit > 0) {
            $all = array_slice($all, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list warehouses';
        $result->data = $all;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Warehouse(
            $id,
            (string) $data['code'],
            (string) $data['name'],
            $data['location'] ?? null,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create warehouse';
        $result->data = $id;

        return $result;
    }

    public function update($id, $data)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new Warehouse(
            $existing->id,
            (string) $data['code'],
            (string) $data['name'],
            $data['location'] ?? null,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update warehouse';
        $result->data = null;

        return $result;
    }

    public function setActive($id, $active)
    {
        $result = new Result();
        $existing = $this->byId[$id] ?? null;

        if ($existing === null) {
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;

            return $result;
        }

        $this->byId[$id] = new Warehouse(
            $existing->id,
            $existing->code,
            $existing->name,
            $existing->location,
            $active,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update warehouse status';
        $result->data = null;

        return $result;
    }

    public function delete($id)
    {
        return $this->setActive($id, false);
    }

    public function findAllActive()
    {
        $allResult = $this->findAll();

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list active warehouses';
        $result->data = array_values(array_filter($allResult->data, function ($w) { return $w->isActive; }));

        return $result;
    }

    public function codeExists($code, $excludeId = null)
    {
        $exists = false;

        foreach ($this->byId as $w) {
            if ($excludeId !== null && $w->id === $excludeId) {
                continue;
            }
            if (strcasecmp($w->code, $code) === 0) {
                $exists = true;
                break;
            }
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to check warehouse code';
        $result->data = $exists;

        return $result;
    }
}
