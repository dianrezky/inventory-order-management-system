<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\CustomerRepositoryInterface;

class CustomerService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $customerRepository;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService for why.
    public function __construct(CustomerRepositoryInterface $customerRepository, EventLogService $eventLogService = null)
    {
        $this->customerRepository = $customerRepository;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'Customer', $id, $description);
        }
    }

    public function listCustomers($search = null, $statuses = null, $name = null, $email = null, $phone = null, $contactPerson = null)
    {
        $findResult = $this->customerRepository->findAll($search, 0, 0, $statuses, $name, $email, $phone, $contactPerson);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function listActiveCustomers()
    {
        $findResult = $this->customerRepository->findAllActive();

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->customerRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    public function search($params = [])
    {
        // $params: search (matches name, email, phone, contact_person), sort_by, sort_dir, filter_active (1 active / 0 inactive / null all), page, per_page (0 = no limit)
        $result = new Result();

        try {
            $search   = isset($params['search'])   ? trim((string) $params['search'])   : null;
            $sortBy   = isset($params['sort_by'])  ? trim((string) $params['sort_by'])  : 'name';
            $sortDir  = isset($params['sort_dir']) ? trim((string) $params['sort_dir']) : 'asc';
            $filter   = array_key_exists('filter_active', $params) ? $params['filter_active'] : null;
            $page     = isset($params['page'])     ? max(1, (int) $params['page'])     : 1;
            $perPage  = isset($params['per_page']) ? max(0, (int) $params['per_page']) : 25;

            $validSortColumns = ['id', 'name', 'email', 'phone', 'contact_person', 'is_active', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns, true)) {
                $sortBy = 'name';
            }

            $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

            $searchResult = $this->customerRepository->search($search, $sortBy, $sortDir, $filter, $page, $perPage);
            if ($searchResult->code !== Result::CODE_SUCCESS) {
                return $searchResult;
            }

            $countResult = $this->customerRepository->countSearch($search, $filter);
            if ($countResult->code !== Result::CODE_SUCCESS) {
                return $countResult;
            }

            $total = $countResult->data;
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The customer list has been loaded.';
            $result->data = [
                'items'       => $searchResult->data,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function createCustomer($input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateCreatePayload($input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);

            $createResult = $this->customerRepository->create($data);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $findResult = $this->customerRepository->findById($createResult->data);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The customer has been created.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'create', $findResult->data->id, "Created customer \"{$findResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateCustomer($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateUpdatePayload($id, $input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);

            $updateResult = $this->customerRepository->update($id, $data);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $findResult = $this->customerRepository->findById($id);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The customer has been updated.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated customer \"{$findResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function setActive($id, $active, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->customerRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $setActiveResult = $this->customerRepository->setActive($id, $active);
            if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                return $setActiveResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The customer status has been updated.';
            $result->data = null;

            $action = $active ? 'activate' : 'deactivate';
            $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " customer \"{$existingResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Validation helpers — return null (pass) or a failure Result (fail)

    private function validateCreatePayload($input)
    {
        // Returns the failure Result for the first invalid field, null when the whole payload passes
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        $email = $this->nullableTrim($input['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->validationResult('Please enter a valid email address.');
        }

        return null;
    }

    private function validateUpdatePayload($id, $input)
    {
        // Checks existence, then name and email format; returns the failure Result for the first problem, null when all pass
        $existingResult = $this->customerRepository->findById($id);
        if ($existingResult->code !== Result::CODE_SUCCESS) {
            return $existingResult;
        }

        if ($existingResult->data === null) {
            return $this->notFoundResult();
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        $email = $this->nullableTrim($input['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->validationResult('Please enter a valid email address.');
        }

        return null;
    }

    private function buildCreateData($input)
    {
        $email = $this->nullableTrim($input['email'] ?? null);

        return [
            'name'           => trim((string) ($input['name'] ?? '')),
            'contact_person' => $this->nullableTrim($input['contact_person'] ?? null),
            'phone'          => $this->nullableTrim($input['phone'] ?? null),
            'email'          => $email,
            'address'        => $this->nullableTrim($input['address'] ?? null),
        ];
    }

    private function notFoundResult()
    {
        $r = new Result();
        $r->code = Result::CODE_VALIDATION;
        $r->info = 'Record not found.';
        $r->data = null;

        return $r;
    }

    private function validationResult($info)
    {
        $r = new Result();
        $r->code = Result::CODE_VALIDATION;
        $r->info = $info;
        $r->data = null;

        return $r;
    }

    private function nullableTrim($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
