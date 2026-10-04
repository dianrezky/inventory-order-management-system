<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// Real HTTP integration test against the running app (container iom_app) and the real MySQL database (iom_db), with no Fake repositories: an Admin POST to /users creates a real row, and a Sales POST is rejected server-side with 403 (BR-017).
final class UserCreationTest extends TestCase
{
    private string $baseUrl;
    private PDO $pdo;

    protected function setUp(): void
    {
        // Base URL and DB connection are overridable for CI via APP_TEST_BASE_URL and DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASSWORD; defaults match the fallbacks in public/index.php.
        $this->baseUrl = \Tests\Support\IntegrationEnvironment::baseUrl();

        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required for this HTTP integration test.');
        }

        $host = (string) (getenv('DB_HOST') ?: 'db');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $name = \Tests\Support\IntegrationEnvironment::databaseName();
        $user = (string) (getenv('DB_USER') ?: 'iom_app');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        try {
            $this->pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (\PDOException $e) {
            self::markTestSkipped('Real MySQL not reachable for integration test: ' . $e->getMessage());
        }

        // Sanity-check the app itself is reachable before running assertions.
        if ($this->httpStatus('GET', '/login', null, null) === null) {
            self::markTestSkipped('App server not reachable at ' . $this->baseUrl);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->prepare('DELETE FROM users WHERE email = ?')
                ->execute(['integration-test-user@example.com']);
        }
    }

    public function testAdminCanCreateUserViaHttpPost(): void
    {
        $email = 'integration-test-user@example.com';
        $this->pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);

        $cookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($cookieJar);

        $loginCsrfToken = $this->fetchCsrfToken('/login', $cookieJar);
        self::assertNotNull($loginCsrfToken, 'Login form should embed a CSRF token');

        $loginStatus = $this->httpStatus('POST', '/login', $cookieJar, [
            'email' => 'admin@example.com',
            'password' => 'admin123',
            '_csrf_token' => $loginCsrfToken,
        ]);
        self::assertSame(302, $loginStatus, 'Admin login should redirect to /dashboard');

        $createCsrfToken = $this->fetchCsrfToken('/users/create', $cookieJar);
        self::assertNotNull($createCsrfToken, 'User create form should embed a CSRF token');

        $createStatus = $this->httpStatus('POST', '/users', $cookieJar, [
            'name' => 'Integration Test User',
            'email' => $email,
            'password' => 'secret123',
            'role' => 'Sales',
            '_csrf_token' => $createCsrfToken,
        ]);

        self::assertSame(302, $createStatus, 'Admin POST /users should succeed and redirect to /users');

        $stmt = $this->pdo->prepare('SELECT name, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row, 'The user created via HTTP POST should exist in the real database');
        self::assertSame('Integration Test User', $row['name']);
        self::assertSame('Sales', $row['role']);
        self::assertSame(1, (int) $row['is_active']);

        @unlink($cookieJar);
    }

    public function testNonAdminCannotCreateUserViaHttpPost(): void
    {
        $email = 'integration-test-user@example.com';
        $this->pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);

        $cookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($cookieJar);

        $loginCsrfToken = $this->fetchCsrfToken('/login', $cookieJar);
        self::assertNotNull($loginCsrfToken, 'Login form should embed a CSRF token');

        $loginStatus = $this->httpStatus('POST', '/login', $cookieJar, [
            'email' => 'sales1@example.com',
            'password' => 'sales123',
            '_csrf_token' => $loginCsrfToken,
        ]);
        self::assertSame(302, $loginStatus, 'Sales login should redirect to /dashboard');

        // Sales is 403'd by requirePermission('users.manage') before requireCsrf()
        // would even be reached, so the session's post-login CSRF token is fine here.
        $createStatus = $this->httpStatus('POST', '/users', $cookieJar, [
            'name' => 'Should Not Exist',
            'email' => $email,
            'password' => 'secret123',
            'role' => 'Admin',
            '_csrf_token' => $loginCsrfToken,
        ]);

        self::assertSame(403, $createStatus, 'Sales POST /users must be rejected server-side (BR-017)');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        self::assertSame(0, (int) $stmt->fetchColumn(), 'No user row should have been created');

        @unlink($cookieJar);
    }

    private function fetchCsrfToken(string $path, string $cookieJar): ?string
    {
        // GETs the page with the given cookie jar and extracts the _csrf_token hidden input, so tests post the same token a real browser would have submitted.
        $html = $this->httpBody('GET', $path, $cookieJar, null);
        if ($html === null) {
            return null;
        }

        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $matches) !== 1) {
            return null;
        }

        return htmlspecialchars_decode($matches[1], ENT_QUOTES);
    }

    private function httpBody(string $method, string $path, ?string $cookieJar, ?array $fields): ?string
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Failed to init curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($cookieJar !== null) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
        }

        $body = curl_exec($ch);

        if (curl_errno($ch) !== 0 || !is_string($body)) {
            curl_close($ch);

            return null;
        }

        curl_close($ch);

        return $body;
    }

    private function httpStatus(string $method, string $path, ?string $cookieJar, ?array $fields): ?int
    {
        // $fields is array<string, string>|null.
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Failed to init curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($cookieJar !== null) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
        }

        curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }
}
