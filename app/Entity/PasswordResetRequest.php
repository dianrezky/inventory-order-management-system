<?php

namespace App\Entity;

// One "forgot password" request. status: 0 = reset email not yet sent, 1 = sent, 2 = gave up.
class PasswordResetRequest
{
    public const STATUS_PENDING = 0;
    public const STATUS_SENT = 1;
    public const STATUS_FAILED = 2;

    public $id;
    public $userId;
    public $tokenHash;
    public $status;
    public $attempts;
    public $lockedUntil;
    public $lastError;
    public $expiresAt;
    public $sentAt;
    public $usedAt;
    public $createdAt;

    // Dates are 'Y-m-d H:i:s' strings in UTC (or null), compared as text by the repositories.
    public function __construct($id, $userId, $tokenHash, $status, $attempts, $lockedUntil, $lastError, $expiresAt, $sentAt, $usedAt, $createdAt)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->tokenHash = $tokenHash;
        $this->status = $status;
        $this->attempts = $attempts;
        $this->lockedUntil = $lockedUntil;
        $this->lastError = $lastError;
        $this->expiresAt = $expiresAt;
        $this->sentAt = $sentAt;
        $this->usedAt = $usedAt;
        $this->createdAt = $createdAt;
    }

    public static function fromArray($row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            $row['token_hash'] ?? null,
            (int) $row['status'],
            (int) $row['attempts'],
            $row['locked_until'] ?? null,
            $row['last_error'] ?? null,
            $row['expires_at'] ?? null,
            $row['sent_at'] ?? null,
            $row['used_at'] ?? null,
            (string) $row['created_at'],
        );
    }
}
