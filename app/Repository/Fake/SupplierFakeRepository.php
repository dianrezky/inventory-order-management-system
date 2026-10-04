<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Supplier;
use App\Repository\Interface\SupplierRepositoryInterface;
use DateTimeImmutable;

class SupplierFakeRepository implements SupplierRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($suppliers = [])
    {
        foreach ($suppliers as $supplier) {
            $this->byId[$supplier->id] = $supplier;
            $this->nextId = max($this->nextId, $supplier->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find supplier';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $contactPerson = null, $email = null)
    {
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($s) use ($search) {
                return stripos($s->name, $search) !== false
                    || stripos($s->contactPerson ?? '', $search) !== false;
            }));
        }

        if ($name !== null && $name !== '') {
            $all = array_values(array_filter($all, function ($s) use ($name) {
                return stripos($s->name, $name) !== false;
            }));
        }

        if ($contactPerson !== null && $contactPerson !== '') {
            $all = array_values(array_filter($all, function ($s) use ($contactPerson) {
                return stripos($s->contactPerson ?? '', $contactPerson) !== false;
            }));
        }

        if ($email !== null && $email !== '') {
            $all = array_values(array_filter($all, function ($s) use ($email) {
                return stripos($s->email ?? '', $email) !== false;
            }));
        }

        if ($statuses !== null && count($statuses) > 0) {
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = true; }
                elseif ($s === 'inactive') { $isActiveValues[] = false; }
            }
            if (count($isActiveValues) > 0) {
                $all = array_values(array_filter($all, function ($s) use ($isActiveValues) {
                    return in_array($s->isActive, $isActiveValues, true);
                }));
            }
        }

        usort($all, function ($a, $b) { return $a->name <=> $b->name; });

        if ($limit > 0) {
            $all = array_slice($all, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list suppliers';
        $result->data = $all;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Supplier(
            $id,
            (string) $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create supplier';
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

        $this->byId[$id] = new Supplier(
            $existing->id,
            (string) $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update supplier';
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

        $this->byId[$id] = new Supplier(
            $existing->id,
            $existing->name,
            $existing->contactPerson,
            $existing->phone,
            $existing->email,
            $existing->address,
            $active,
            $existing->createdAt,
            new DateTimeImmutable()
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update supplier status';
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
        $result->info = 'Success to list active suppliers';
        $result->data = array_values(array_filter($allResult->data, function ($s) { return $s->isActive; }));

        return $result;
    }
}
