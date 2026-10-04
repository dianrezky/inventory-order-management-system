<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\Customer;
use App\Repository\Interface\CustomerRepositoryInterface;
use DateTimeImmutable;

class CustomerFakeRepository implements CustomerRepositoryInterface
{
    private $byId = [];
    private $nextId = 1;

    public function __construct($customers = [])
    {
        foreach ($customers as $customer) {
            $this->byId[$customer->id] = $customer;
            $this->nextId = max($this->nextId, $customer->id + 1);
        }
    }

    public function findById($id)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find customer';
        $result->data = $this->byId[$id] ?? null;

        return $result;
    }

    public function findAll($search = null, $limit = 0, $offset = 0, $statuses = null, $name = null, $email = null, $phone = null, $contactPerson = null)
    {
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($c) use ($search) {
                return stripos($c->name, $search) !== false
                    || stripos($c->email ?? '', $search) !== false
                    || stripos($c->phone ?? '', $search) !== false;
            }));
        }

        if ($name !== null && $name !== '') {
            $all = array_values(array_filter($all, function ($c) use ($name) {
                return stripos($c->name, $name) !== false;
            }));
        }

        if ($email !== null && $email !== '') {
            $all = array_values(array_filter($all, function ($c) use ($email) {
                return stripos($c->email ?? '', $email) !== false;
            }));
        }

        if ($phone !== null && $phone !== '') {
            $all = array_values(array_filter($all, function ($c) use ($phone) {
                return stripos($c->phone ?? '', $phone) !== false;
            }));
        }

        if ($contactPerson !== null && $contactPerson !== '') {
            $all = array_values(array_filter($all, function ($c) use ($contactPerson) {
                return stripos($c->contactPerson ?? '', $contactPerson) !== false;
            }));
        }

        if ($statuses !== null && count($statuses) > 0) {
            $isActiveValues = [];
            foreach ($statuses as $s) {
                if ($s === 'active') { $isActiveValues[] = true; }
                elseif ($s === 'inactive') { $isActiveValues[] = false; }
            }
            if (!empty($isActiveValues)) {
                $all = array_values(array_filter($all, function ($c) use ($isActiveValues) {
                    return in_array($c->isActive, $isActiveValues, true);
                }));
            }
        }

        usort($all, function ($a, $b) { return $a->name <=> $b->name; });

        if ($limit > 0) {
            $all = array_slice($all, $offset, $limit);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to list customers';
        $result->data = $all;

        return $result;
    }

    public function create($data)
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Customer(
            $id,
            (string) $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to create customer';
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

        $this->byId[$id] = new Customer(
            $existing->id,
            (string) $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $existing->isActive,
            $existing->createdAt,
            new DateTimeImmutable(),
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update customer';
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

        $this->byId[$id] = new Customer(
            $existing->id,
            $existing->name,
            $existing->contactPerson,
            $existing->phone,
            $existing->email,
            $existing->address,
            $active,
            $existing->createdAt,
            new DateTimeImmutable(),
        );

        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to update customer status';
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
        $result->info = 'Success to list active customers';
        $result->data = array_values(array_filter($allResult->data, function ($c) { return $c->isActive; }));

        return $result;
    }

    public function search($search, $sortBy, $sortDir, $filter, $page, $perPage)
    {
        $all = array_values($this->byId);

        if ($search !== null && $search !== '') {
            $all = array_values(array_filter($all, function ($c) use ($search) {
                return stripos($c->name, $search) !== false
                    || stripos($c->email ?? '', $search) !== false
                    || stripos($c->phone ?? '', $search) !== false
                    || stripos($c->contactPerson ?? '', $search) !== false;
            }));
        }

        if ($filter !== null) {
            $target = (bool) $filter;
            $all = array_values(array_filter($all, function ($c) use ($target) {
                return $c->isActive === $target;
            }));
        }

        $sortDir = $sortDir === 'desc' ? -1 : 1;

        usort($all, function ($a, $b) use ($sortBy, $sortDir) {
            $colA = $this->getSortColumn($a, $sortBy);
            $colB = $this->getSortColumn($b, $sortBy);

            if ($colA === $colB) {
                return 0;
            }

            return $colA < $colB ? -1 * $sortDir : 1 * $sortDir;
        });

        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $all = array_slice($all, $offset, $perPage);
        }

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to search customers';
        $result->data = $all;

        return $result;
    }

    public function countSearch($search, $filter)
    {
        $searchResult = $this->search($search, 'name', 'asc', $filter, 0, 0);

        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to count customers';
        $result->data = count($searchResult->data);

        return $result;
    }

    private function getSortColumn($customer, $sortBy)
    {
        // 'name' falls through to default, which also sorts by name.
        return match ($sortBy) {
            'id' => $customer->id,
            'email' => $customer->email ?? '',
            'phone' => $customer->phone ?? '',
            'contact_person' => $customer->contactPerson ?? '',
            'is_active' => $customer->isActive ? 1 : 0,
            'created_at' => $customer->createdAt ? $customer->createdAt->format('Y-m-d H:i:s') : '',
            'updated_at' => $customer->updatedAt ? $customer->updatedAt->format('Y-m-d H:i:s') : '',
            default => $customer->name,
        };
    }
}
