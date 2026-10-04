<?php

namespace App\Service;

use App\Core\ClockInterface;
use App\Core\Result;
use App\Core\SessionManager;
use App\Core\SystemClock;
use App\Entity\Role;
use App\Repository\Interface\UserRepositoryInterface;

// AUTH-01/AUTH-02. Depends only on the repository interface and the session wrapper — never touches PDO directly (ARCH-01).
class AuthService
{
    private $userRepository;
    private $session;
    private $eventLogService;
    private $clock;

    // $eventLogService and $clock are optional (nullable, default null)
    // rather than required dependencies: many existing Unit/Integration
    // tests construct this Service directly with only (repository,
    // session). Making them required would mean touching every one of
    // those construction sites. When Container wires this Service in
    // production it always passes a real EventLogService and SystemClock.
    public function __construct(UserRepositoryInterface $userRepository, SessionManager $session, EventLogService $eventLogService = null, ClockInterface $clock = null)
    {
        $this->userRepository = $userRepository;
        $this->session = $session;
        $this->eventLogService = $eventLogService;
        $this->clock = $clock ?? new SystemClock();
    }

    public function login($email, $password)
    {
        $result = new Result();

        try {
            $email = trim($email);

            $findResult = $this->userRepository->findByEmail($email);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $user = $findResult->data;

            if ($user === null || !$user->isActive || !password_verify($password, $user->passwordHash)) {
                $result->code = 1;
                $result->info = 'The email address or password you entered is incorrect.';
                $result->data = null;

                if ($this->eventLogService !== null) {
                    // Actor is unknown/unauthenticated for a failed attempt — user_id stays null.
                    // The attempted email is not stored in description to avoid enumerating
                    // valid vs invalid accounts from the log itself.
                    $this->eventLogService->record(null, 'login_failed', null, null, 'Failed login attempt');
                }
            } else {
                // Prevent session fixation (BR-006)
                $this->session->regenerate();

                // Issue a fresh CSRF token on login so a pre-authentication token (attacker-primed) is never reused post-login
                $this->session->remove('_csrf_token');

                $this->session->set('user_id', $user->id);
                $this->session->set('role', $user->role instanceof Role ? $user->role->value : (string) $user->role);
                $this->session->set('user_name', $user->name);
                $this->session->set('last_activity', $this->clock->now()->getTimestamp());

                $result->code = 0;
                $result->info = 'You have been signed in.';
                $result->data = $user;

                if ($this->eventLogService !== null) {
                    $this->eventLogService->record($user->id, 'login', 'User', $user->id, "{$user->name} logged in");
                }
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function logout()
    {
        // Capture the actor before destroy() clears the session — logging
        // must never delay or block the actual logout, so this stays a
        // best-effort read of already-known session data, not a fresh query.
        $userId = $this->session->get('user_id');
        $userName = $this->session->get('user_name');

        $this->session->destroy();

        if ($this->eventLogService !== null && is_int($userId)) {
            $description = $userName !== null ? "{$userName} logged out" : 'User logged out';
            $this->eventLogService->record($userId, 'logout', 'User', $userId, $description);
        }
    }

    public function currentUser()
    {
        $userId = $this->session->get('user_id');

        if (!is_int($userId)) {
            return null;
        }

        // Idle timeout: the cookie lifetime alone is absolute and client-controlled, so the server also expires a session left unused for longer than SESSION_LIFETIME
        $lastActivity = $this->session->get('last_activity');
        $lifetime = $this->session->getLifetime();
        $expired = $lifetime > 0 && is_int($lastActivity) && $this->clock->now()->getTimestamp() - $lastActivity > $lifetime;

        $user = null;
        if (!$expired) {
            $findResult = $this->userRepository->findById($userId);
            $user = $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
        }

        // An expired idle timeout, a missing user, or a deactivated account all
        // invalidate the session — otherwise a revoked account keeps access until
        // the cookie lifetime expires.
        if ($user === null || !$user->isActive) {
            $this->session->destroy();

            return null;
        }

        // Sync session role with the authoritative DB value (handles role changes made while the user was logged in)
        $this->session->set('role', $user->role instanceof Role ? $user->role->value : (string) $user->role);
        $this->session->set('last_activity', $this->clock->now()->getTimestamp());

        return $user;
    }
}
