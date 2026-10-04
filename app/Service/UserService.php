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
            $validationResult = $this->validateUserUpdate($existingResult, $id, $input, $actorId);
            if ($validationResult !== null) {
                return $validationResult;
            }
            $name = trim((string) ($input['name'] ?? ''));
            $newPassword = (string) ($input['password'] ?? '');
            $updateResult = $this->userRepository->update($id, [
                'name' => $name,
                'email' => trim((string) ($input['email'] ?? '')),
                'role' => (string) ($input['role'] ?? ''),
            ]);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }
            $errorResult = null;
            if ($newPassword !== '') {
                $passwordResult = $this->userRepository->updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT));
                if ($passwordResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $passwordResult;
                }
            }
            if ($errorResult === null) {
                $updatedResult = $this->userRepository->findById($id);
                if ($updatedResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $updatedResult;
                } else {
                    $result->code = Result::CODE_SUCCESS;
                    $result->info = 'The user account has been updated.';
                    $result->data = $updatedResult->data;
                    $this->logEvent($actorId, 'update', $id, "Updated user \"{$name}\"" . ($newPassword !== '' ? ' (password changed)' : ''));
                }
            }
            if ($errorResult !== null) {
                $result->code = $errorResult->code;
                $result->info = $errorResult->info;
                $result->data = $errorResult->data;
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }
        return $result;
    }

    // Profile validation also prevents an actor from removing their own administrative role.
    private function validateUserUpdate($existingResult, $id, $input, $actorId)
    {
        $validationResult = null;
        if ($existingResult->code !== Result::CODE_SUCCESS) {
            $validationResult = $existingResult;
        } elseif ($existingResult->data === null) {
            $validationResult = $this->notFoundResult();
        } else {
            $validationResult = $this->validateUpdatePayloadWithPassword($id, $input);
            $role = $existingResult->data->role;
            $currentRole = $role instanceof \App\Entity\Role ? $role->value : (string) $role;
            if ($validationResult === null && $actorId !== null && (int) $actorId === (int) $id && (string) ($input['role'] ?? '') !== $currentRole) {
                $validationResult = new Result();
                $validationResult->code = Result::CODE_VALIDATION;
                $validationResult->info = 'You cannot change your own role.';
                $validationResult->data = ['role' => 'You cannot change your own role.'];
            }
        }
        return $validationResult;
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
            // Each step runs only while no earlier step has produced a failing
            // Result; the first failure is returned, otherwise $result is populated.
            $existingResult = $this->userRepository->findById($id);
            $errorResult = null;
            if ($existingResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $existingResult;
            } elseif ($existingResult->data === null) {
                $errorResult = $this->notFoundResult();
            }

            if ($errorResult === null) {
                $errorResult = $this->validateProfilePayload($id, $name, $email);
            }

            if ($errorResult === null) {
                $updateResult = $this->userRepository->update($id, [
                    'name'  => $name,
                    'email' => $email,
                    'role'  => $existingResult->data->role->value,
                ]);
                if ($updateResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $updateResult;
                }
            }

            if ($errorResult === null) {
                $updatedResult = $this->userRepository->findById($id);
                if ($updatedResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $updatedResult;
                } else {
                    $result->code = Result::CODE_SUCCESS;
                    $result->info = 'Your profile has been updated.';
                    $result->data = $updatedResult->data;

                    $this->logEvent($id, 'update', $id, "Updated own profile");
                }
            }

            if ($errorResult !== null) {
                return $errorResult;
            }
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

        $errorResult = null;
        if ($name === '') {
            $errorResult = $this->validationResult('Name is required.');
        } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errorResult = $this->validationResult('Please enter a valid email address.');
        } elseif (Role::tryFrom($role) === null) {
            $errorResult = $this->validationResult('Please select a valid role.');
        }

        return $errorResult;
    }

    private function validateCreatePayloadWithPassword($input)
    {
        $errorResult = $this->validateCreatePayload($input);

        if ($errorResult === null) {
            $password = (string) ($input['password'] ?? '');
            if ($password === '' || strlen($password) < 6) {
                $errorResult = $this->validationResult('Password must be at least 6 characters.');
            }
        }

        if ($errorResult === null) {
            $email = trim((string) ($input['email'] ?? ''));
            $existsResult = $this->userRepository->emailExists($email);
            if ($existsResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $existsResult;
            } elseif ($existsResult->data) {
                $errorResult = $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
            }
        }

        return $errorResult;
    }

    private function validateUpdatePayload($id, $input)
    {
        $name  = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $role  = (string) ($input['role'] ?? '');

        $errorResult = null;
        if ($name === '') {
            $errorResult = $this->validationResult('Name is required.');
        } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errorResult = $this->validationResult('Please enter a valid email address.');
        } elseif (Role::tryFrom($role) === null) {
            $errorResult = $this->validationResult('Please select a valid role.');
        }

        if ($errorResult === null) {
            $existsResult = $this->userRepository->emailExists($email, $id);
            if ($existsResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $existsResult;
            } elseif ($existsResult->data) {
                $errorResult = $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
            }
        }

        return $errorResult;
    }

    private function validateUpdatePayloadWithPassword($id, $input)
    {
        $errorResult = $this->validateUpdatePayload($id, $input);

        if ($errorResult === null) {
            $newPassword = (string) ($input['password'] ?? '');
            if ($newPassword !== '' && strlen($newPassword) < 6) {
                $errorResult = $this->validationResult('Password must be at least 6 characters.');
            }
        }

        return $errorResult;
    }

    private function validateProfilePayload($id, $name, $email)
    {
        $errorResult = null;
        if ($name === '') {
            $errorResult = $this->validationResult('Name is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorResult = $this->validationResult('Please enter a valid email address.');
        }

        if ($errorResult === null) {
            $existsResult = $this->userRepository->emailExists($email, $id);
            if ($existsResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $existsResult;
            } elseif ($existsResult->data) {
                $errorResult = $this->validationResult(self::MESSAGE_EMAIL_TAKEN);
            }
        }

        return $errorResult;
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
