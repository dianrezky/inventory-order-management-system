<?php

/**
 * Global Configuration
 *
 * Assumes .env has already been loaded into $_ENV by the caller (public/index.php).
 * Centralizes all application configuration from environment variables.
 */

// ================================================================
// ROUTING CONSTANTS
// ================================================================

/**
 * Regex patterns for route constraints
 */
if (!defined('REGEX_CHARACTER')) {
    define('REGEX_CHARACTER', '[a-zA-Z\d_-]+');
}
if (!defined('REGEX_PATTERN')) {
    define('REGEX_PATTERN', '[a-zA-Z][a-zA-Z\d_-]*');
}
if (!defined('REGEX_NUMBER')) {
    define('REGEX_NUMBER', '\d+');
}
if (!defined('REGEX_ID_TOKEN')) {
    // Hex-encoded obfuscated id token (see App\Core\IdObfuscator) used for {id}
    // route segments that used to be plain REGEX_NUMBER — never widen this to
    // accept raw digits, or obfuscation is bypassable by just passing the id.
    define('REGEX_ID_TOKEN', '[0-9a-f]+');
}

/**
 * Route type constants
 */
if (!defined('ROUTE_TYPE_LITERAL')) {
    define('ROUTE_TYPE_LITERAL', 'literal');
}
if (!defined('ROUTE_TYPE_SEGMENT')) {
    define('ROUTE_TYPE_SEGMENT', 'segment');
}

// ================================================================
// DATABASE & SERVICE CONFIGURATION
// ================================================================

$globalConfig = [
    'db' => [
        'host'     => $_ENV['DB_HOST'] ?? 'db', // defaults let the cron job (which does not inherit container env) run from .env alone
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name'     => $_ENV['DB_NAME'] ?? 'inventory_order_management',
        'user'     => $_ENV['DB_USER'] ?? 'iom_app',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    'session' => [
        'name'     => $_ENV['SESSION_NAME'] ?? 'iom_session',
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 3600),
    ],
    'redis' => [
        'host' => $_ENV['REDIS_HOST'] ?? 'redis',
        'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        // sessions use database 0; the read cache (role permissions) uses its own database
        'cache_database' => (int) ($_ENV['REDIS_CACHE_DATABASE'] ?? 1),
    ],
    'memcached' => [
        'host' => $_ENV['MEMCACHED_HOST'] ?? 'memcached',
        'port' => (int) ($_ENV['MEMCACHED_PORT'] ?? 11211),
    ],
    // Public base URL used to build links in emails (e.g. https://ioms.example.com, no trailing slash).
    'app_url' => $_ENV['APP_URL'] ?? '',
    // Outbound mail. MAIL_TRANSPORT=smtp sends through MAIL_HOST; =file writes .eml files to storage/mail (local dev).
    'mail' => [
        'transport' => $_ENV['MAIL_TRANSPORT'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? '',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // tls (STARTTLS), ssl (implicit TLS) or none
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Inventory & Order Management',
    ],
    'id_obfuscation' => [
        'key' => $_ENV['ID_OBFUSCATION_KEY'] ?? '',
    ],
    'minio' => [
        // 'endpoint' is the address the PHP app (inside the "app" container)
        // uses to talk to MinIO and is what gets SigV4-signed. This project
        // reuses the MinIO instance already running for the portfolio-apps
        // monorepo it lives in (container "portfolio-minio", started
        // separately via deploy/minio-docker-compose.yml) rather than
        // running its own — host.docker.internal is how a container reaches
        // a service published on the host instead of a sibling container.
        // For local development outside Docker, use http://127.0.0.1:9000.
        // For Docker on Windows/Mac, use http://host.docker.internal:9000.
        // For Docker-in-Docker or same network, use http://minio:9000.
        'endpoint' => $_ENV['MINIO_ENDPOINT'] ?? 'http://host.docker.internal:9000',
        'region' => $_ENV['MINIO_REGION'] ?? 'us-east-1',
        'access_key' => $_ENV['MINIO_ACCESS_KEY'] ?? '',
        'secret_key' => $_ENV['MINIO_SECRET_KEY'] ?? '',
        // 'bucket' — shared bucket already used by all apps in portfolio-apps.
        // IOMS isolates its objects under the "ioms/" namespace prefix
        // (see MinioClient::IOMS_PREFIX). Do NOT create a new bucket.
        'bucket' => $_ENV['MINIO_BUCKET'] ?? 'portfolio-uploads',
        // 'public_base_url' is what gets embedded into products.image_path
        // and loaded straight by the user's BROWSER — must be an address
        // reachable from outside the Docker network (the published host
        // port), which is why this is deliberately a separate setting from
        // 'endpoint' above rather than reusing it.
        'public_base_url' => $_ENV['MINIO_PUBLIC_URL'] ?? 'http://localhost:9000',
    ],
];

// Store global configuration for application-wide access
$GLOBALS['globalConfig'] = $globalConfig;

// Return final global configuration
return $globalConfig;
