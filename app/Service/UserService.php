<?php

namespace App\Service;

use App\Core\Result;
use App\Entity\Role;
use App\Repository\Interface\UserRepositoryInterface;

class UserService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;
    public const MESSAGE_EMAIL_TAKEN      = 'This email is already in use.';

    private $userRepository;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService for why.
    public function __construct(UserRepositoryInterface $userRepository, EventLogService $eventLogService = null)
    {
        $this->userRepository = $userRepository;
        $this->eventLogService = $eventLogService;
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'User', $id, $description);
        }
    }

    public function listUsers($search = null, $statuses = null, $roles = null, $name = null, $email = null)
    {
        $findResult = $this->userRepository->findAll($search, $statuses, $roles, $name, $email);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->userRepository->findById($id);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
    }

    public function createUser($input, $actorId = null)
    {
        $result = new Result();

        try {
            $v = $this->validateCreatePayloadWithPassword($input);
            if ($v !== null) {
                return $v;
            }

            $name     = trim((string) ($input['name'] ?? ''));
            $email    = trim((string) ($input['email'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            $role     = (string) ($input['role'] ?? '');

            $createResult = $this->userRepository->create([
                'name'         => $name,
                'email'        => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role'         => $role,
            ]);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }

            $createdResult = $this->userRepository->findById($createResult->data);
            if ($createdResult->code !== Result::CODE_SUCCESS) {
                return $createdResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The user account has been created.';
            $result->data = $createdResult->data;

            $this->logEvent($actorId, 'create', $createdResult->data->id, "Created user \"{$name}\" ({$role})");
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateUser($id, $input, $actorId = null)
    {
        $result = new Result();

        try {
            $existingResult = $this->userRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $v = $this->validateUpdatePayloadWithPassword($id, $input);
            // Changing your own role would drop the permission to manage users mid-session (and can leave no Admin at all)
            $currentRole = $existingResult->data->role instanceof \App\Entity\Role ? $existingResult->data->role->value : (string) $existingResult->data->role;
            if ($v === null && $actorId !== null && (int) $actorId === (int) $id && (string) ($input['role'] ?? '') !== $currentRole) {
                $v = new Result();
                $v->code = Result::CODE_VALIDATION;
                $v->info = 'You cannot change your own role.';
                $v->data = ['role' => 'You cannot change your own role.'];
            }
            if ($v !== null) {
                return $v;
            }

            $name       = trim((string) ($input['name'] ?? ''));
            $email      = trim((string) ($input['email'] ?? ''));
            $role       = (string) ($input['role'] ?? '');
            $newPassword = (string) ($input['password'] ?? '');

            $updateResult = $this->userRepository->update($id, [
                'name'  => $name,
                'email' => $email,
                'role'  => $role,
            ]);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            if ($newPassword !== '') {
                $passwordResult = $this->userRepository->updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT));
                if ($passwordResult->code !== Result::CODE_SUCCESS) {
                    return $passwordResult;
                }
            }

            $updatedResult = $this->userRepository->findById($id);
            if ($updatedResult->code !== Result::CODE_SUCCESS) {
                return $updatedResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The user account has been updated.';
            $result->data = $updatedResult->data;

            $this->logEvent($actorId, 'update', $id, "Updated user \"{$name}\"" . ($newPassword !== '' ? ' (password changed)' : ''));
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
            $existingResult = $this->userRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            // An Admin deactivating their own account would lock themselves out
            // (and, as the last active Admin, the whole system): only another
            // Admin — who is by definition still active — may do it.
            if (!$active && $actorId !== null && (int) $actorId === (int) $id) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'You cannot deactivate your own account.';
                $result->data = null;
            } else {
                $setActiveResult = $this->userRepository->setActive($id, $active);
                if ($setActiveResult->code !== Result::CODE_SUCCESS) {
                    return $setActiveResult;
                }

                $result->code = Result::CODE_SUCCESS;
                $result->info = 'The user account status has been updated.';
                $result->data = null;

                $action = $active ? 'activate' : 'deactivate';
                $this->logEvent($actorId, $action, $id, ($active ? 'Activated' : 'Deactivated') . " user \"{$existingResult->data->name}\"");
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function updateProfile($id, $name, $email)
    {
        $result = new Result();

        try {
            $existingResult = $this->userRepository->findById($id);
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                return $existingResult;
            }

            if ($existingResult->data === null) {
                return $this->notFoundResult();
            }

            $validation = $this->validateProfilePayload($id, $name, $email);
            if ($validation !== null) {
                return $validation;
            }

            $updateResult = $this->userRepository->update($id, [
                'name'  => $name,
                'email' => $email,
                'role'  => $existingResult->data->role->value,
            ]);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $updatedResult = $this->userRepository->findById($id);
            if ($updatedResult->code !== Result::CODE_SUCCESS) {
                return $updatedResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Your profile has been updated.';
            $result->data = $updatedResult->data;

            $this->logEvent($id, 'update', $id, "Updated own profile");
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
        $name  = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $role  = (string) ($input['role'] ?? '');

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->validationResult('Please enter a valid email address.');
        }

        if (Role::tryFrom($role) === null) {
            return $this->validationResult('Please select a valid role.');
        }

        return null;
    }

    private function validateCreatePayloadWithPassword($input)
    {
        $v = $this->validateCreatePayload($input);
        if ($v !== null) {
            return $v;
        }

        $password = (string) ($input['password'] ?? '');
        if ($password === '' || strlen($password) < 6) {
            return $this->validationResult('Password must be at least 6 characters.');
        }

        $email = trim((string) ($input['email'] ?? ''));
        $existsResult = $this->userRepository->emailExists($email);
        if ($existsResult->code !== Result::CODE_SUCCESS) {
            return $existsResult;
        }

        if ($existsResult->data) {
            return $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
        }

        return null;
    }

    private function validateUpdatePayload($id, $input)
    {
        $name  = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $role  = (string) ($input['role'] ?? '');

        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->validationResult('Please enter a valid email address.');
        }

        if (Role::tryFrom($role) === null) {
            return $this->validationResult('Please select a valid role.');
        }

        $existsResult = $this->userRepository->emailExists($email, $id);
        if ($existsResult->code !== Result::CODE_SUCCESS) {
            return $existsResult;
        }

        if ($existsResult->data) {
            return $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
        }

        return null;
    }

    private function validateUpdatePayloadWithPassword($id, $input)
    {
        $v = $this->validateUpdatePayload($id, $input);
        if ($v !== null) {
            return $v;
        }

        $newPassword = (string) ($input['password'] ?? '');
        if ($newPassword !== '' && strlen($newPassword) < 6) {
            return $this->validationResult('Password must be at least 6 characters.');
        }

        return null;
    }

    private function validateProfilePayload($id, $name, $email)
    {
        if ($name === '') {
            return $this->validationResult('Name is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->validationResult('Please enter a valid email address.');
        }

        $existsResult = $this->userRepository->emailExists($email, $id);
        if ($existsResult->code !== Result::CODE_SUCCESS) {
            return $existsResult;
        }

        if ($existsResult->data) {
            return $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
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
}
