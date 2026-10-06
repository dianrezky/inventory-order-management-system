<?php

namespace App\Repository\Interface;

// Persistence for the forgot-password flow. All timestamps are 'Y-m-d H:i:s' strings supplied by the caller's
// ClockInterface (never the DB clock), so every comparison uses one time source and tests can drive time.
interface PasswordResetRepositoryInterface
{
    // Rate-limit bookkeeping: one row per request, whether or not the email belongs to a user.
    public function recordAttempt($ipAddress, $emailHash, $at);

    public function countAttemptsByIp($ipAddress, $since);

    public function countAttemptsByEmailHash($emailHash, $since);

    // True when the user already has a not-yet-sent (status 0) request created at/after $since.
    public function hasPendingRequest($userId, $since);

    public function create($userId, $createdAt);

    // Pending requests older than $before are marked failed (status 2) without sending.
    public function expireStalePending($before);

    // Requests the cron job may work on: status 0, not locked, attempts below the cap. Oldest first.
    public function findClaimable($now, $maxAttempts, $limit);

    // Atomically takes the row: succeeds (data === true) for exactly one concurrent caller. Stores the token
    // hash, increments attempts and locks the row until $lockUntil.
    public function claim($id, $tokenHash, $now, $lockUntil, $maxAttempts);

    // status 0 -> 1, sets the expiry and releases the lock.
    public function markSent($id, $sentAt, $expiresAt);

    // Send failed: keep status 0 and retry after $retryAt, or give up (status 2) when $giveUp.
    public function markFailed($id, $error, $retryAt, $giveUp);

    // Row with status 1, unused and unexpired for this token hash, locked FOR UPDATE inside the caller's transaction.
    public function findUsableByTokenHash($tokenHash, $now);

    // Atomic single-use switch: data === true only for the first caller.
    public function markUsed($id, $usedAt);

    // Burns every still-open request of the user (after a successful reset).
    public function invalidateOpenForUser($userId, $usedAt);
}
