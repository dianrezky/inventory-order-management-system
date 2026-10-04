<?php

namespace Tests\Support;

// Every fixture connection, including child workers, must opt into a separate DB.
final class IntegrationEnvironment
{
    public static function databaseName()
    {
        $testName = trim((string) getenv('TEST_DB_NAME'));
        $applicationName = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
        if ($testName === '' || strcasecmp($testName, $applicationName) === 0) {
            throw new \RuntimeException('Set TEST_DB_NAME to a dedicated database different from DB_NAME before running integration tests.');
        }
        if (preg_match('/^[a-zA-Z0-9_]+$/', $testName) !== 1) {
            throw new \RuntimeException('TEST_DB_NAME must contain only letters, digits and underscores.');
        }

        return $testName;
    }

    public static function baseUrl()
    {
        $baseUrl = trim((string) getenv('APP_TEST_BASE_URL'));
        $httpDatabase = trim((string) getenv('APP_TEST_DB_NAME'));
        if ($baseUrl === '' || $httpDatabase !== self::databaseName()) {
            throw new \RuntimeException('Set APP_TEST_BASE_URL for a dedicated test app and APP_TEST_DB_NAME equal to TEST_DB_NAME.');
        }

        return rtrim($baseUrl, '/');
    }
}
