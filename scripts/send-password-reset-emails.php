#!/usr/bin/env php
<?php
/**
 * Password-reset email job — run by the "cron" container every 3 minutes
 * (docker/cron/password-reset.cron), or by hand:
 *   docker compose exec cron php scripts/send-password-reset-emails.php
 *
 * Picks only requests whose status is 0 (email not yet sent), emails the reset link and sets status 1.
 * Claiming is an atomic UPDATE, so overlapping runs cannot double-send. Failed sends are retried with a
 * back-off up to PasswordResetService::MAX_SEND_ATTEMPTS, then the request is marked status 2.
 * Prints counts only — never addresses or tokens. Exit 0 = ran (even if some sends failed), 1 = could not run.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php'; // NOSONAR
} else {
    fwrite(STDERR, "Error: autoload.php not found. Run 'composer install' first.\n");
    exit(1);
}

if (is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

try {
    // global.php reads required keys directly, so the cron job needs the same .env as the app.
    $config = require $root . '/config/global.php'; // NOSONAR
    $container = new App\Core\Container($config);
    $service = $container->getPasswordResetService();
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: could not start — ' . $e->getMessage() . "\n");
    exit(1);
}

$result = $service->sendPending();
$summary = $result->data ?? [];

printf(
    "[%s] password-reset emails: sent=%d failed=%d skipped=%d expired=%d\n",
    gmdate('Y-m-d H:i:s') . ' UTC',
    $summary['sent'] ?? 0,
    $summary['failed'] ?? 0,
    $summary['skipped'] ?? 0,
    $summary['expired'] ?? 0
);

if ($result->code !== App\Core\Result::CODE_SUCCESS) {
    fwrite(STDERR, 'Error: ' . $result->info . "\n");
    exit(1);
}
exit(0);
