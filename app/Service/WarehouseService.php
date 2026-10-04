<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\WarehouseRepositoryInterface;

class WarehouseService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $warehouseRepository;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService for why.
    public function __construct(WarehouseRepositoryInterface $warehouseRepository, EventLogService $eventLogService = null)
    {
        $this->warehouseRepository = $warehouseRepository;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'Warehouse', $id, $description);
        }
    }

    public function listWarehouses($search = null, $statuses = null, $code = null, $name = null, $location = null)
    {
        $findResult = $this->warehouseRepository->findAll($search, 0, 0, $statuses, $code, $name, $location);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function listActiveWarehouses()
    {
        $findResult = $this->warehouseRepository->findAllActive();

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->warehouseRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    public function createWarehouse($input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateCreatePayload($input);
            if ($validation !== null) {
                return $validation;
            }

            $code = strtoupper(trim((string) ($input['code'] ?? '')));
            $name = trim((string) ($input['name'] ?? ''));

            $codeExistsResult = $this->warehouseRepository->codeExists($code);
            if ($codeExistsResult->code !== Result::CODE_SUCCESS) {
                return $codeExistsResult;
            }

            if ($codeExistsResult->data) {
                return $this->validationResult('This code is already in use.');
            }

            $createResult = $this->warehouseRepository->create([
                'code'     => $code,
                'name'     => $name,
                'location' => $this->nullableTrim($input['location'] ?? null),
            ]);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $findResult = $this->warehouseRepository->findById($createResult->data);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The warehouse has been created.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'create', $findResult->data->id, "Created warehouse \"{$findResult->data->name}\"");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateWarehouse($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $validation = $this->validateUpdatePayload($id, $input);
            if ($validation !== null) {
                return $validation;
            }

            $code = strtoupper(trim((string) ($input['code'] ?? '')));
            $name = trim((string) ($input['name'] ?? ''));

            $updateResult = $this->warehouseRepository->update($id, [
                'code'     => $code,
                'name'     => $name,
                'location' => $this->nullableTrim($input['location'] ?? null),
            ]);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $findResult = $this->warehouseRepository->findById($id);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The warehouse has been updated.';
            $result->data = $findResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated warehouse \"{$findResult->data->name}\"");
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
            $existingResult = $this->warehouseRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $setActiveResult = $this->warehouseRepository->setActive($id, $active);
            if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                return $setActiveResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The warehouse status has been updated.';
            $result->data = null;

            $action = $active ? 'activate' : 'deactivate';
            $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " warehouse \"{$existingResult->data->name}\"");
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
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));

        if ($code === '') {
            return $this->validationResult('Code is required.');
        }

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        return null;
    }

    private function validateUpdatePayload($id, $input)
    {
        $existingResult = $this->warehouseRepository->findById($id);
        if ($existingResult->code !== Result::CODE_SUCCESS) {
            return $existingResult;
        }

        if ($existingResult->data === null) {
            return $this->notFoundResult();
        }

        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));

        if ($code === '') {
            return $this->validationResult('Code is required.');
        }

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        $codeExistsResult = $this->warehouseRepository->codeExists($code, $id);
        if ($codeExistsResult->code !== Result::CODE_SUCCESS) {
            return $codeExistsResult;
        }

        if ($codeExistsResult->data) {
            return $this->validationResult('This code is already in use.');
        }

        return null;
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
