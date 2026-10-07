<?php

namespace App\Repository\MySQL;

use App\Core\Database;
use App\Core\Result;
use App\Entity\PasswordResetRequest;
use App\Repository\Interface\PasswordResetRepositoryInterface;

// All queries are prepared statements. The conditional UPDATEs below are the concurrency guards:
// a row can be claimed or consumed by exactly one caller because the WHERE clause re-checks the state.
class PasswordResetMySQLRepository implements PasswordResetRepositoryInterface
{
    private $db;

    private const COLUMNS = 'id, user_id, token_hash, status, attempts, locked_until, last_error, expires_at, sent_at, used_at, created_at';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function recordAttempt($ipAddress, $emailHash, $at)
    {
        return $this->run(function () use ($ipAddress, $emailHash, $at) {
            $this->execute(
                'INSERT INTO password_reset_attempts (ip_address, email_hash, created_at) VALUES (:ip, :email_hash, :at)',
                ['ip' => $ipAddress, 'email_hash' => $emailHash, 'at' => $at]
            );

            return null;
        });
    }

    public function countAttemptsByIp($ipAddress, $since)
    {
        return $this->run(function () use ($ipAddress, $since) {
            return (int) $this->scalar(
                'SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = :ip AND created_at >= :since',
                ['ip' => $ipAddress, 'since' => $since]
            );
        });
    }

    public function countAttemptsByEmailHash($emailHash, $since)
    {
        return $this->run(function () use ($emailHash, $since) {
            return (int) $this->scalar(
                'SELECT COUNT(*) FROM password_reset_attempts WHERE email_hash = :email_hash AND created_at >= :since',
                ['email_hash' => $emailHash, 'since' => $since]
            );
        });
    }

    public function hasPendingRequest($userId, $since)
    {
        return $this->run(function () use ($userId, $since) {
            return (int) $this->scalar(
                'SELECT COUNT(*) FROM password_reset_requests WHERE user_id = :user_id AND status = 0 AND created_at >= :since',
                ['user_id' => $userId, 'since' => $since]
            ) > 0;
        });
    }

    public function create($userId, $createdAt)
    {
        return $this->run(function () use ($userId, $createdAt) {
            $this->execute(
                'INSERT INTO password_reset_requests (user_id, status, attempts, created_at) VALUES (:user_id, 0, 0, :created_at)',
                ['user_id' => $userId, 'created_at' => $createdAt]
            );

            return (int) $this->db->lastInsertId();
        });
    }

    public function expireStalePending($before)
    {
        return $this->run(function () use ($before) {
            return $this->execute(
                "UPDATE password_reset_requests SET status = 2, locked_until = NULL, last_error = 'Not sent in time' WHERE status = 0 AND created_at < :before",
                ['before' => $before]
            );
        });
    }

    public function findClaimable($now, $maxAttempts, $limit)
    {
        return $this->run(function () use ($now, $maxAttempts, $limit) {
            $stmt = $this->db->pdo()->prepare(
                'SELECT ' . self::COLUMNS . ' FROM password_reset_requests'
                . ' WHERE status = 0 AND attempts < :max_attempts AND (locked_until IS NULL OR locked_until <= :now)'
                . ' ORDER BY id ASC LIMIT ' . (int) $limit
            );
            $stmt->execute(['max_attempts' => (int) $maxAttempts, 'now' => $now]);

            return array_map([PasswordResetRequest::class, 'fromArray'], $stmt->fetchAll());
        });
    }

    public function claim($id, $tokenHash, $now, $lockUntil, $maxAttempts)
    {
        return $this->run(function () use ($id, $tokenHash, $now, $lockUntil, $maxAttempts) {
            return $this->execute(
                'UPDATE password_reset_requests SET token_hash = :token_hash, attempts = attempts + 1, locked_until = :lock_until'
                . ' WHERE id = :id AND status = 0 AND attempts < :max_attempts AND (locked_until IS NULL OR locked_until <= :now)',
                ['token_hash' => $tokenHash, 'lock_until' => $lockUntil, 'id' => $id, 'max_attempts' => (int) $maxAttempts, 'now' => $now]
            ) === 1;
        });
    }

    public function markSent($id, $sentAt, $expiresAt)
    {
        return $this->run(function () use ($id, $sentAt, $expiresAt) {
            return $this->execute(
                'UPDATE password_reset_requests SET status = 1, sent_at = :sent_at, expires_at = :expires_at, locked_until = NULL, last_error = NULL'
                . ' WHERE id = :id AND status = 0',
                ['sent_at' => $sentAt, 'expires_at' => $expiresAt, 'id' => $id]
            ) === 1;
        });
    }

    public function markFailed($id, $error, $retryAt, $giveUp)
    {
        return $this->run(function () use ($id, $error, $retryAt, $giveUp) {
            return $this->execute(
                'UPDATE password_reset_requests SET status = :status, token_hash = NULL, locked_until = :retry_at, last_error = :error'
                . ' WHERE id = :id AND status = 0',
                [
                    'status' => $giveUp ? PasswordResetRequest::STATUS_FAILED : PasswordResetRequest::STATUS_PENDING,
                    'retry_at' => $giveUp ? null : $retryAt,
                    'error' => mb_substr((string) $error, 0, 255),
                    'id' => $id,
                ]
            ) === 1;
        });
    }

    public function findUsableByTokenHash($tokenHash, $now)
    {
        return $this->run(function () use ($tokenHash, $now) {
            $stmt = $this->db->pdo()->prepare(
                'SELECT ' . self::COLUMNS . ' FROM password_reset_requests'
                . ' WHERE token_hash = :token_hash AND status = 1 AND used_at IS NULL AND expires_at > :now FOR UPDATE'
            );
            $stmt->execute(['token_hash' => $tokenHash, 'now' => $now]);
            $row = $stmt->fetch();

            return $row === false ? null : PasswordResetRequest::fromArray($row);
        });
    }

    public function markUsed($id, $usedAt)
    {
        return $this->run(function () use ($id, $usedAt) {
            return $this->execute(
                'UPDATE password_reset_requests SET used_at = :used_at WHERE id = :id AND used_at IS NULL',
                ['used_at' => $usedAt, 'id' => $id]
            ) === 1;
        });
    }

    public function invalidateOpenForUser($userId, $usedAt)
    {
        return $this->run(function () use ($userId, $usedAt) {
            return $this->execute(
                'UPDATE password_reset_requests SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
                ['used_at' => $usedAt, 'user_id' => $userId]
            );
        });
    }

    private function run(callable $operation)
    {
        $result = new Result();

        try {
            $result->data = $operation();
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success';
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = Result::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    private function execute($sql, array $params)
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function scalar($sql, array $params)
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }
}
