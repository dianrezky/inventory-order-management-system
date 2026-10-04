<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\IdObfuscator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// DEC-012 (Segregation of Duties) end-to-end over real HTTP against the running app and real MySQL: Sales can never approve a Sales Order, while an Admin can - including one the Admin created, because creator identity plays no part in the decision.
//
// NOTE: SO ids appear in URLs as IdObfuscator-encoded hex tokens (see
// app/Core/IdObfuscator.php), not raw integers, so this test decodes them
// back to real ids with the same key the app container uses (ID_OBFUSCATION_KEY)
// whenever it needs to query sales_orders by id directly.
final class SalesOrderApprovalPolicyTest extends TestCase
{
    private string $baseUrl;
    private PDO $pdo;
    private IdObfuscator $idObfuscator;
    private int $customerId;
    private int $warehouseId;
    private int $productId;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim((string) (getenv('APP_TEST_BASE_URL') ?: 'http://localhost:8080'), '/');

        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required for this HTTP integration test.');
        }

        $this->idObfuscator = new IdObfuscator((string) (getenv('ID_OBFUSCATION_KEY') ?: ''));

        $host = (string) (getenv('DB_HOST') ?: 'db');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $name = (string) (getenv('DB_NAME') ?: 'inventory_order_management');
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

        if ($this->httpStatus('GET', '/login', null, null) === null) {
            self::markTestSkipped('App server not reachable at ' . $this->baseUrl);
        }

        $this->customerId = (int) $this->pdo->query('SELECT id FROM customers ORDER BY id ASC LIMIT 1')->fetchColumn();
        $this->warehouseId = (int) $this->pdo->query("SELECT id FROM warehouses WHERE code = 'WH-JKT'")->fetchColumn();
        $this->productId = (int) $this->pdo->query('SELECT id FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        $ids = $this->pdo->query(
            "SELECT id FROM sales_orders WHERE note LIKE 'integration-test-br001-%'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->pdo->prepare('DELETE FROM sales_order_items WHERE sales_order_id = ?')->execute([$id]);
        }
        $this->pdo->exec("DELETE FROM sales_orders WHERE note LIKE 'integration-test-br001-%'");
    }

    public function testSalesCannotApproveOwnSalesOrderViaHttp(): void
    {
        // Segregation of Duties: a Sales user approving his own SO must get 403 and leave the status untouched - enforced by the requirePermission('sales_orders.approve') guard (Admin-only, per role_permissions) on /approve and, as defense in depth, by SalesOrderPolicy::assertCanDecide() in the service.
        $cookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($cookieJar);

        $this->loginAs($cookieJar, 'sales1@example.com', 'sales123');

        $soToken = $this->createSalesOrder($cookieJar, 'beni-self-approve');
        $this->submitForApproval($cookieJar, $soToken);
        self::assertSame('PendingApproval', $this->soStatus($soToken));

        // Beni (Sales, and the creator) attempts to approve his own SO.
        $approveCsrf = $this->fetchCsrfToken('/sales-orders/' . $soToken, $cookieJar);
        self::assertNotNull($approveCsrf, 'SO detail page should embed a CSRF token');

        $status = $this->httpStatus('POST', '/sales-orders/' . $soToken . '/approve', $cookieJar, [
            '_csrf_token' => $approveCsrf,
        ]);

        self::assertSame(403, $status, 'Sales approving a sales order must be rejected server-side (DEC-012)');
        self::assertSame('PendingApproval', $this->soStatus($soToken), 'SO status must remain unchanged after a rejected approval');

        @unlink($cookieJar);
    }

    public function testAdminCanApproveAnotherUsersSalesOrderViaHttp(): void
    {
        $beniCookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($beniCookieJar);
        $this->loginAs($beniCookieJar, 'sales1@example.com', 'sales123');

        $soToken = $this->createSalesOrder($beniCookieJar, 'beni-admin-approves');
        $this->submitForApproval($beniCookieJar, $soToken);
        self::assertSame('PendingApproval', $this->soStatus($soToken));

        $ritaCookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($ritaCookieJar);
        $this->loginAs($ritaCookieJar, 'admin@example.com', 'admin123');

        $approveCsrf = $this->fetchCsrfToken('/sales-orders/' . $soToken, $ritaCookieJar);
        self::assertNotNull($approveCsrf, 'SO detail page should embed a CSRF token');

        $status = $this->httpStatus('POST', '/sales-orders/' . $soToken . '/approve', $ritaCookieJar, [
            '_csrf_token' => $approveCsrf,
        ]);

        self::assertSame(302, $status, 'Admin approving a different user\'s SO should succeed and redirect');
        self::assertSame('Approved', $this->soStatus($soToken), 'SO status must become Approved');

        @unlink($beniCookieJar);
        @unlink($ritaCookieJar);
    }

    public function testAdminCanApproveTheirOwnSelfCreatedSalesOrderViaHttp(): void
    {
        // DEC-012 case: "creator identity plays no part in the decision" —
        // Rita (Admin) approving an SO she personally created must succeed,
        // exercising SalesOrderPolicy::assertCanDecide() (which takes no
        // SalesOrder/creator id at all, only the actor's role).
        $cookieJar = tempnam(sys_get_temp_dir(), 'iom_cookies_');
        self::assertIsString($cookieJar);
        $this->loginAs($cookieJar, 'admin@example.com', 'admin123');

        $soToken = $this->createSalesOrder($cookieJar, 'rita-self-approve');
        $this->submitForApproval($cookieJar, $soToken);
        self::assertSame('PendingApproval', $this->soStatus($soToken));

        $approveCsrf = $this->fetchCsrfToken('/sales-orders/' . $soToken, $cookieJar);
        self::assertNotNull($approveCsrf, 'SO detail page should embed a CSRF token');

        $status = $this->httpStatus('POST', '/sales-orders/' . $soToken . '/approve', $cookieJar, [
            '_csrf_token' => $approveCsrf,
        ]);

        self::assertSame(302, $status, 'Admin approving their own self-created SO must succeed (DEC-012)');
        self::assertSame('Approved', $this->soStatus($soToken), 'SO status must become Approved');

        @unlink($cookieJar);
    }

    private function soStatus(string $soToken): string
    {
        $soId = $this->idObfuscator->decode($soToken);
        self::assertNotNull($soId, 'SO token should decode back to a real id with ID_OBFUSCATION_KEY');

        $stmt = $this->pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
        $stmt->execute([$soId]);

        return (string) $stmt->fetchColumn();
    }

    private function loginAs(string $cookieJar, string $email, string $password): void
    {
        $csrf = $this->fetchCsrfToken('/login', $cookieJar);
        self::assertNotNull($csrf, 'Login form should embed a CSRF token');

        $status = $this->httpStatus('POST', '/login', $cookieJar, [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => $csrf,
        ]);
        self::assertSame(302, $status, "Login as {$email} should redirect to /dashboard");
    }

    private function createSalesOrder(string $cookieJar, string $noteSuffix): string
    {
        // Creates a Draft SO via a real HTTP POST and returns its IdObfuscator-encoded
        // id token, parsed from the redirect Location header (e.g. "/sales-orders/4f2a9c").
        $createCsrf = $this->fetchCsrfToken('/sales-orders/create', $cookieJar);
        self::assertNotNull($createCsrf, 'SO create form should embed a CSRF token');

        $location = $this->httpLocation('POST', '/sales-orders', $cookieJar, [
            'customer_id' => (string) $this->customerId,
            'source_warehouse_id' => (string) $this->warehouseId,
            'order_date' => date('Y-m-d'),
            'note' => 'integration-test-br001-' . $noteSuffix,
            'item_product_id' => [(string) $this->productId],
            'item_qty' => ['1'],
            'item_sale_price' => ['10.00'],
            '_csrf_token' => $createCsrf,
        ]);

        self::assertNotNull($location, 'SO creation should redirect to the new SO detail page');

        // IDs in URLs are IdObfuscator-encoded hex tokens (bin2hex), not plain
        // decimal ids, so the token itself is [0-9a-f]+ - a plain \d+ would
        // only grab a meaningless leading digit run out of the hex string.
        if (preg_match('#/sales-orders/([0-9a-f]+)#', $location, $matches) !== 1) {
            throw new RuntimeException('Could not parse SO id token from redirect location: ' . $location);
        }

        $token = $matches[1];
        self::assertNotNull(
            $this->idObfuscator->decode($token),
            'Redirect location should contain a valid obfuscated SO id token (check ID_OBFUSCATION_KEY is set identically for app and test)',
        );

        return $token;
    }

    private function submitForApproval(string $cookieJar, string $soToken): void
    {
        $submitCsrf = $this->fetchCsrfToken('/sales-orders/' . $soToken, $cookieJar);
        self::assertNotNull($submitCsrf, 'SO detail page should embed a CSRF token');

        $status = $this->httpStatus('POST', '/sales-orders/' . $soToken . '/submit', $cookieJar, [
            '_csrf_token' => $submitCsrf,
        ]);
        self::assertSame(302, $status, 'Submitting the SO for approval should redirect');
    }

    private function fetchCsrfToken(string $path, string $cookieJar): ?string
    {
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
        // $fields is array<string, mixed>|null and supports scalar and array (list) values.
        $ch = $this->buildCurl($method, $path, $cookieJar, $fields);
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
        // $fields is array<string, mixed>|null.
        $ch = $this->buildCurl($method, $path, $cookieJar, $fields);
        curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }

    private function httpLocation(string $method, string $path, ?string $cookieJar, ?array $fields): ?string
    {
        // POSTs and returns the redirect Location header, or null when the response is not a redirect.
        $ch = $this->buildCurl($method, $path, $cookieJar, $fields);
        curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            curl_close($ch);

            return null;
        }

        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        return is_string($location) && $location !== '' ? $location : null;
    }

    private function buildCurl(string $method, string $path, ?string $cookieJar, ?array $fields)
    {
        // $fields is array<string, mixed>|null.
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

        return $ch;
    }
}
