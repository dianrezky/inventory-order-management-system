#!/usr/bin/env php
<?php
/**
 * Applies database/migrations/*.sql in filename order. Every migration is idempotent
 * (CREATE TABLE IF NOT EXISTS ...), so re-running is safe; fresh installs already get the
 * same DDL from database/schema.sql.
 *
 *   docker compose exec app php scripts/migrate.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php'; // NOSONAR

if (is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'] ?? 'db', (int) ($_ENV['DB_PORT'] ?? 3306), $_ENV['DB_NAME'] ?? 'inventory_order_management'),
        $_ENV['DB_USER'] ?? 'iom_app',
        $_ENV['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: database connection failed — ' . $e->getMessage() . "\n");
    exit(1);
}

$files = glob($root . '/database/migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    try {
        // Strip "-- ..." comments (migrations must not contain '--' inside string literals), then run each ';'-terminated statement.
        $sql = preg_replace('/--.*$/m', '', (string) file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $statement) {
            $pdo->exec($statement);
        }
        echo 'applied ' . basename($file) . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Error in ' . basename($file) . ': ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo 'migrations done (' . count($files) . ' file(s))' . PHP_EOL;
