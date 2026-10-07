<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Entity\PasswordResetRequest;
use App\Repository\Interface\PasswordResetRepositoryInterface;

// In-memory double mirroring the MySQL repository's conditional-update semantics (claim/markUsed succeed once).
class PasswordResetFakeRepository implements PasswordResetRepositoryInterface
{
    public $requests = [];
    public $attempts = [];
    private $nextId = 1;

    public function recordAttempt($ipAddress, $emailHash, $at)
    {
        $this->attempts[] = ['ip' => $ipAddress, 'email_hash' => $emailHash, 'at' => $at];

        return $this->ok(null);
    }

    public function countAttemptsByIp($ipAddress, $since)
    {
        return $this->ok(count(array_filter($this->attempts, fn ($a) => $a['ip'] === $ipAddress && $a['at'] >= $since)));
    }

    public function countAttemptsByEmailHash($emailHash, $since)
    {
        return $this->ok(count(array_filter($this->attempts, fn ($a) => $a['email_hash'] === $emailHash && $a['at'] >= $since)));
    }

    public function hasPendingRequest($userId, $since)
    {
        foreach ($this->requests as $r) {
            if ($r->userId === $userId && $r->status === 0 && $r->createdAt >= $since) {
                return $this->ok(true);
            }
        }

        return $this->ok(false);
    }

    public function create($userId, $createdAt)
    {
        $id = $this->nextId++;
        $this->requests[$id] = new PasswordResetRequest($id, $userId, null, 0, 0, null, null, null, null, null, $createdAt);

        return $this->ok($id);
    }

    public function expireStalePending($before)
    {
        $n = 0;
        foreach ($this->requests as $r) {
            if ($r->status === 0 && $r->createdAt < $before) {
                $r->status = 2;
                $r->lockedUntil = null;
                $n++;
            }
        }

        return $this->ok($n);
    }

    public function findClaimable($now, $maxAttempts, $limit)
    {
        $rows = [];
        foreach ($this->requests as $r) {
            if ($r->status === 0 && $r->attempts < $maxAttempts && ($r->lockedUntil === null || $r->lockedUntil <= $now)) {
                $rows[] = clone $r;
            }
        }

        return $this->ok(array_slice($rows, 0, $limit));
    }

    public function claim($id, $tokenHash, $now, $lockUntil, $maxAttempts)
    {
        $r = $this->requests[$id] ?? null;
        if ($r === null || $r->status !== 0 || $r->attempts >= $maxAttempts || ($r->lockedUntil !== null && $r->lockedUntil > $now)) {
            return $this->ok(false);
        }
        $r->tokenHash = $tokenHash;
        $r->attempts++;
        $r->lockedUntil = $lockUntil;

        return $this->ok(true);
    }

    public function markSent($id, $sentAt, $expiresAt)
    {
        $r = $this->requests[$id] ?? null;
        if ($r === null || $r->status !== 0) {
            return $this->ok(false);
        }
        $r->status = 1;
        $r->sentAt = $sentAt;
        $r->expiresAt = $expiresAt;
        $r->lockedUntil = null;
        $r->lastError = null;

        return $this->ok(true);
    }

    public function markFailed($id, $error, $retryAt, $giveUp)
    {
        $r = $this->requests[$id] ?? null;
        if ($r === null || $r->status !== 0) {
            return $this->ok(false);
        }
        $r->status = $giveUp ? 2 : 0;
        $r->tokenHash = null;
        $r->lockedUntil = $giveUp ? null : $retryAt;
        $r->lastError = $error;

        return $this->ok(true);
    }

    public function findUsableByTokenHash($tokenHash, $now)
    {
        foreach ($this->requests as $r) {
            if ($r->tokenHash === $tokenHash && $r->status === 1 && $r->usedAt === null && $r->expiresAt > $now) {
                return $this->ok(clone $r);
            }
        }

        return $this->ok(null);
    }

    public function markUsed($id, $usedAt)
    {
        $r = $this->requests[$id] ?? null;
        if ($r === null || $r->usedAt !== null) {
            return $this->ok(false);
        }
        $r->usedAt = $usedAt;

        return $this->ok(true);
    }

    public function invalidateOpenForUser($userId, $usedAt)
    {
        $n = 0;
        foreach ($this->requests as $r) {
            if ($r->userId === $userId && $r->usedAt === null) {
                $r->usedAt = $usedAt;
                $n++;
            }
        }

        return $this->ok($n);
    }

    private function ok($data)
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success';
        $result->data = $data;

        return $result;
    }
}
