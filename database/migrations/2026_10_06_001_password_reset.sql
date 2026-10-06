-- Forgot-password flow. Idempotent (CREATE TABLE IF NOT EXISTS): safe to re-run.
-- Applied automatically by scripts/migrate.php (called from scripts/deploy-vps.sh);
-- fresh installs get the same tables from database/schema.sql (keep both in sync).
--
-- password_reset_requests: one row per "I forgot my password" request.
--   status  0 = reset email NOT yet sent (the cron job picks only these rows)
--           1 = reset email sent
--           2 = gave up (max send attempts reached, or the request went stale / the user is gone)
--   token_hash is NULL until the cron job sends the email: the raw token is
--   generated at send time, only its SHA-256 is stored, and it is never written
--   anywhere else (so a DB leak cannot be turned into reset links).
--   locked_until doubles as the claim lock (a worker owns the row until then)
--   and the retry back-off after a failed send.
-- password_reset_attempts: every request, known email or not, for rate limiting.
CREATE TABLE IF NOT EXISTS password_reset_requests (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED  NOT NULL,
    token_hash   CHAR(64)         NULL,
    status       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME         NULL,
    last_error   VARCHAR(255)     NULL,
    expires_at   DATETIME         NULL,
    sent_at      DATETIME         NULL,
    used_at      DATETIME         NULL,
    created_at   DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_password_reset_token (token_hash),
    KEY idx_password_reset_status_created (status, created_at),
    KEY idx_password_reset_user (user_id),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45)     NOT NULL,
    email_hash CHAR(64)        NOT NULL,   -- SHA-256 of the lowercased email; the address itself is not stored
    created_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_password_reset_attempts_ip (ip_address, created_at),
    KEY idx_password_reset_attempts_email (email_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
