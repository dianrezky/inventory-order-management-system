<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\SupplierRepositoryInterface;

class SupplierService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $supplierRepository;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService for why.
    public function __construct(SupplierRepositoryInterface $supplierRepository, EventLogService $eventLogService = null)
    {
        $this->supplierRepository = $supplierRepository;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'Supplier', $id, $description);
        }
    }

    public function listSuppliers($search = null, $statuses = null, $name = null, $contactPerson = null, $email = null)
    {
        $findResult = $this->supplierRepository->findAll($search, 0, 0, $statuses, $name, $contactPerson, $email);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function listActiveSuppliers()
    {
        $findResult = $this->supplierRepository->findAllActive();

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->supplierRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    public function createSupplier($input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateCreatePayload($input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);

            $createResult = $this->supplierRepository->create($data);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $findResult = $this->supplierRepository->findById($createResult->data);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The supplier has been created.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'create', $findResult->data->id, "Created supplier \"{$findResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateSupplier($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateUpdatePayload($id, $input);
            if ($validation !== null) {
                return $validation;
            }

            $data = $this->buildCreateData($input);

            $updateResult = $this->supplierRepository->update($id, $data);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $findResult = $this->supplierRepository->findById($id);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The supplier has been updated.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated supplier \"{$findResult->data->name}\"");
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
            $existingResult = $this->supplierRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $setActiveResult = $this->supplierRepository->setActive($id, $active);
            if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                return $setActiveResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The supplier status has been updated.';
            $result->data = null;

            $action = $active ? 'activate' : 'deactivate';
            $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " supplier \"{$existingResult->data->name}\"");
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
        $existingResult = $this->supplierRepository->findById($id);
        $errorResult = null;
        if ($existingResult->code !== Result::CODE_SUCCESS) {
            $errorResult = $existingResult;
        } elseif ($existingResult->data === null) {
            $errorResult = $this->notFoundResult();
        }

        if ($errorResult === null) {
            $name = trim((string) ($input['name'] ?? ''));
            $email = $this->nullableTrim($input['email'] ?? null);
            if ($name === '') {
                $errorResult = $this->validationResult('Name is required.');
            } elseif ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errorResult = $this->validationResult('Please enter a valid email address.');
            }
        }

        return $errorResult;
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
