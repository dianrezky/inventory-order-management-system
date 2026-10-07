<?php

namespace App\Service;

use App\Core\ClockInterface;
use App\Core\Mail\MailerInterface;
use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Entity\User;
use App\Repository\Interface\PasswordResetRepositoryInterface;
use App\Repository\Interface\UserRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

// Forgot-password flow.
//   1. requestReset(): web request. Only records a pending row (status 0); it never sends mail and never reveals
//      whether the email exists.
//   2. sendPending(): run by the cron job every 3 minutes. For each status-0 row it atomically claims the row,
//      generates the one-time token (only its SHA-256 is stored), emails the link and flips the row to status 1.
//   3. resetPassword(): the reset form. The token is single-use and short-lived; the new password is bcrypt-hashed
//      exactly like UserService does.
class PasswordResetService
{
    public const MESSAGE_GENERIC_REQUEST = 'If that email address belongs to an active account, we have queued a password reset email for it. It can take a few minutes to arrive.';
    public const MESSAGE_INVALID_TOKEN = 'This password reset link is invalid or has expired. Please request a new one.';
    public const MESSAGE_PASSWORD_SHORT = 'Password must be at least 6 characters.';
    public const MESSAGE_PASSWORD_MISMATCH = 'The two passwords do not match.';

    public const MIN_PASSWORD_LENGTH = 6; // same rule as UserService

    public const TOKEN_TTL_MINUTES = 30;          // lifetime of the link, counted from the moment it is emailed
    public const PENDING_TTL_MINUTES = 60;        // a request not sent within this time is abandoned (status 2)
    public const CLAIM_LOCK_MINUTES = 5;          // how long a worker owns a row while it is sending
    public const RETRY_BACKOFF_MINUTES = 3;       // multiplied by the attempt number after a failed send
    public const MAX_SEND_ATTEMPTS = 5;
    public const RATE_WINDOW_MINUTES = 60;
    public const RATE_LIMIT_PER_IP = 10;
    public const RATE_LIMIT_PER_EMAIL = 3;

    private $userRepository;
    private $resetRepository;
    private $mailer;
    private $transactionManager;
    private $clock;
    private $eventLogService;
    private $appUrl;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordResetRepositoryInterface $resetRepository,
        MailerInterface $mailer,
        TransactionManagerInterface $transactionManager,
        ClockInterface $clock,
        EventLogService $eventLogService = null,
        $appUrl = ''
    ) {
        $this->userRepository = $userRepository;
        $this->resetRepository = $resetRepository;
        $this->mailer = $mailer;
        $this->transactionManager = $transactionManager;
        $this->clock = $clock;
        $this->eventLogService = $eventLogService;
        $this->appUrl = rtrim((string) $appUrl, '/');
    }

    // Always returns CODE_SUCCESS with the same message, so the response (and its timing, apart from one INSERT)
    // does not depend on whether the email exists, is active, or was rate limited.
    public function requestReset($email, $ipAddress)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = self::MESSAGE_GENERIC_REQUEST;
        $result->data = null;

        try {
            $email = strtolower(trim((string) $email));
            $ipAddress = (string) $ipAddress;
            if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return $result;
            }

            $emailHash = hash('sha256', $email);
            $now = $this->now();
            $windowStart = $this->format($this->clock->now()->modify('-' . self::RATE_WINDOW_MINUTES . ' minutes'));

            $byIp = $this->resetRepository->countAttemptsByIp($ipAddress, $windowStart);
            $byEmail = $this->resetRepository->countAttemptsByEmailHash($emailHash, $windowStart);
            if ($byIp->code !== Result::CODE_SUCCESS || $byEmail->code !== Result::CODE_SUCCESS) {
                return $result;
            }

            $this->resetRepository->recordAttempt($ipAddress, $emailHash, $now);

            if ($byIp->data >= self::RATE_LIMIT_PER_IP || $byEmail->data >= self::RATE_LIMIT_PER_EMAIL) {
                return $result;
            }

            $findResult = $this->userRepository->findByEmail($email);
            $user = $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
            if (!$user instanceof User || !$user->isActive) {
                return $result;
            }

            $pendingSince = $this->format($this->clock->now()->modify('-' . self::PENDING_TTL_MINUTES . ' minutes'));
            $pending = $this->resetRepository->hasPendingRequest($user->id, $pendingSince);
            if ($pending->code !== Result::CODE_SUCCESS || $pending->data === true) {
                return $result;
            }

            $this->resetRepository->create($user->id, $now);
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
        }

        return $result;
    }

    // Cron entry point. Returns data = ['sent' => n, 'failed' => n, 'skipped' => n, 'expired' => n].
    public function sendPending($limit = 50)
    {
        $result = new Result();
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'expired' => 0];

        try {
            if ($this->appUrl === '') {
                $result->code = Result::CODE_INTERNAL;
                $result->info = 'APP_URL is not configured; reset links cannot be built.';
                $result->data = $summary;

                return $result;
            }

            $stale = $this->resetRepository->expireStalePending(
                $this->format($this->clock->now()->modify('-' . self::PENDING_TTL_MINUTES . ' minutes'))
            );
            if ($stale->code === Result::CODE_SUCCESS) {
                $summary['expired'] = (int) $stale->data;
            }

            $candidates = $this->resetRepository->findClaimable($this->now(), self::MAX_SEND_ATTEMPTS, (int) $limit);
            if ($candidates->code !== Result::CODE_SUCCESS) {
                $result->code = Result::CODE_INTERNAL;
                $result->info = $candidates->info;
                $result->data = $summary;

                return $result;
            }

            foreach ($candidates->data as $request) {
                $outcome = $this->sendOne($request);
                $summary[$outcome]++;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Processed pending password reset requests';
            $result->data = $summary;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = $summary;
        }

        return $result;
    }

    // Returns 'sent', 'failed' or 'skipped' (someone else claimed it first, or the user is gone).
    private function sendOne($request)
    {
        // Generated before the claim and stored by the claim itself: only the worker that wins the atomic
        // UPDATE ever holds a token that matches the stored hash, so two workers can never both email a link.
        $token = bin2hex(random_bytes(32));
        $now = $this->now();
        $lockUntil = $this->format($this->clock->now()->modify('+' . self::CLAIM_LOCK_MINUTES . ' minutes'));

        $claim = $this->resetRepository->claim($request->id, hash('sha256', $token), $now, $lockUntil, self::MAX_SEND_ATTEMPTS);
        if ($claim->code !== Result::CODE_SUCCESS || $claim->data !== true) {
            return 'skipped';
        }

        $attempt = $request->attempts + 1;

        $findResult = $this->userRepository->findById($request->userId);
        $user = $findResult->code === Result::CODE_SUCCESS ? $findResult->data : null;
        if ($findResult->code === Result::CODE_SUCCESS && (!$user instanceof User || !$user->isActive)) {
            $this->resetRepository->markFailed($request->id, 'User no longer active', null, true);

            return 'skipped';
        }
        if (!$user instanceof User) {
            return $this->fail($request->id, 'User lookup failed', $attempt);
        }

        try {
            $this->mailer->send($user->email, $user->name, 'Reset your password', $this->buildBody($user, $token));
        } catch (\Throwable $e) {
            // The exception text never contains the token; it is stored (truncated) for the operator.
            error_log('Password reset mail for request ' . $request->id . ' failed: ' . $e->getMessage());

            return $this->fail($request->id, $e->getMessage(), $attempt);
        }

        $expiresAt = $this->format($this->clock->now()->modify('+' . self::TOKEN_TTL_MINUTES . ' minutes'));
        // Retried because a concurrent worker can make MySQL abort one UPDATE with a deadlock; the mail is already out.
        for ($try = 0; $try < 3; $try++) {
            $sent = $this->resetRepository->markSent($request->id, $this->now(), $expiresAt);
            if ($sent->code === Result::CODE_SUCCESS) {
                break;
            }
        }
        if ($sent->code !== Result::CODE_SUCCESS || $sent->data !== true) {
            // The mail went out but the row could not be flipped. The row stays locked until the claim expires
            // and is then retried; log loudly so a duplicate email is explainable.
            error_log('Password reset request ' . $request->id . ': mail sent but status update failed');
        }

        if ($this->eventLogService !== null) {
            $this->eventLogService->record(null, 'password_reset_email_sent', 'User', $user->id, 'Password reset email sent');
        }

        return 'sent';
    }

    private function fail($requestId, $error, $attempt)
    {
        $giveUp = $attempt >= self::MAX_SEND_ATTEMPTS;
        $retryAt = $this->format($this->clock->now()->modify('+' . (self::RETRY_BACKOFF_MINUTES * $attempt) . ' minutes'));
        $this->resetRepository->markFailed($requestId, $error, $retryAt, $giveUp);

        return 'failed';
    }

    public function resetPassword($token, $password, $confirmation)
    {
        $result = new Result();

        try {
            $token = (string) $token;
            $password = (string) $password;

            if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                return $this->validation($result, self::MESSAGE_PASSWORD_SHORT);
            }
            if (!hash_equals($password, (string) $confirmation)) {
                return $this->validation($result, self::MESSAGE_PASSWORD_MISMATCH);
            }
            if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
                return $this->validation($result, self::MESSAGE_INVALID_TOKEN);
            }

            $tokenHash = hash('sha256', $token);
            $now = $this->now();
            $userId = null;

            $this->transactionManager->beginTransaction();
            try {
                $found = $this->resetRepository->findUsableByTokenHash($tokenHash, $now);
                if ($found->code !== Result::CODE_SUCCESS) {
                    throw new \RuntimeException('Reset lookup failed');
                }
                $request = $found->data;
                $used = $request === null ? null : $this->resetRepository->markUsed($request->id, $now);
                if ($request === null || $used->code !== Result::CODE_SUCCESS || $used->data !== true) {
                    $this->transactionManager->rollBack();

                    return $this->validation($result, self::MESSAGE_INVALID_TOKEN);
                }

                $userResult = $this->userRepository->findById($request->userId);
                $user = $userResult->code === Result::CODE_SUCCESS ? $userResult->data : null;
                if ($userResult->code !== Result::CODE_SUCCESS) {
                    throw new \RuntimeException('User lookup failed');
                }
                if (!$user instanceof User || !$user->isActive) {
                    $this->transactionManager->rollBack();

                    return $this->validation($result, self::MESSAGE_INVALID_TOKEN);
                }

                $update = $this->userRepository->updatePassword($user->id, password_hash($password, PASSWORD_BCRYPT));
                if ($update->code !== Result::CODE_SUCCESS) {
                    throw new \RuntimeException('Password update failed');
                }
                $this->resetRepository->invalidateOpenForUser($user->id, $now);

                $this->transactionManager->commit();
                $userId = $user->id;
            } catch (\Throwable $e) {
                $this->transactionManager->rollBack();
                throw $e;
            }

            if ($this->eventLogService !== null) {
                $this->eventLogService->record($userId, 'password_reset', 'User', $userId, 'Password reset via email link');
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Your password has been changed. You can now sign in.';
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    private function buildBody($user, $token)
    {
        // The token sits in the URL fragment, which browsers never send to the server, so it stays out of
        // access logs and Referer headers; the reset page reads it client-side and POSTs it.
        $link = $this->appUrl . '/reset-password#token=' . $token;

        return "Hello {$user->name},\n\n"
            . "We received a request to reset the password of your Inventory & Order Management account.\n"
            . 'Open the link below to choose a new password. It works once and expires in ' . self::TOKEN_TTL_MINUTES . " minutes:\n\n"
            . $link . "\n\n"
            . "If you did not ask for this, you can ignore this email; your password stays unchanged.\n";
    }

    private function validation(Result $result, $message)
    {
        $result->code = Result::CODE_VALIDATION;
        $result->info = $message;
        $result->data = null;

        return $result;
    }

    private function now()
    {
        return $this->format($this->clock->now());
    }

    private function format(DateTimeImmutable $time)
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
