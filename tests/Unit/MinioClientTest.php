<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\MinioClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MinioClientTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────
    // CONSTRUCTOR VALIDATION
    // ─────────────────────────────────────────────────────────────────

    public function testConstructSucceedsWithAllRequiredValues(): void
    {
        $client = new MinioClient(
            'http://minio:9000',
            'us-east-1',
            'my-access-key',
            'my-secret-key',
            'portfolio-uploads',
            'http://localhost:9000'
        );

        $this->assertSame('ioms/', $client::IOMS_PREFIX);
    }

    public function testConstructThrowsWhenEndpointIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('endpoint is not configured');

        new MinioClient(
            '',
            'us-east-1',
            'my-access-key',
            'my-secret-key',
            'portfolio-uploads',
            'http://localhost:9000'
        );
    }

    public function testConstructThrowsWhenAccessKeyIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('access key is not configured');

        new MinioClient(
            'http://minio:9000',
            'us-east-1',
            '',
            'my-secret-key',
            'portfolio-uploads',
            'http://localhost:9000'
        );
    }

    public function testConstructThrowsWhenSecretKeyIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secret key is not configured');

        new MinioClient(
            'http://minio:9000',
            'us-east-1',
            'my-access-key',
            '',
            'portfolio-uploads',
            'http://localhost:9000'
        );
    }

    public function testConstructThrowsWhenBucketIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bucket is not configured');

        new MinioClient(
            'http://minio:9000',
            'us-east-1',
            'my-access-key',
            'my-secret-key',
            '',
            'http://localhost:9000'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // IOMS_PREFIX CONSTANT
    // ─────────────────────────────────────────────────────────────────

    public function testIomsPrefixIsCorrectNamespace(): void
    {
        $this->assertSame('ioms/', MinioClient::IOMS_PREFIX);
    }

    // ─────────────────────────────────────────────────────────────────
    // buildObjectKey()
    // ─────────────────────────────────────────────────────────────────

    private function makeClient(): MinioClient
    {
        return new MinioClient(
            'http://minio:9000',
            'us-east-1',
            'ak',
            'sk',
            'portfolio-uploads',
            'http://localhost:9000'
        );
    }

    public function testBuildObjectKeyPrependsIomsPrefix(): void
    {
        $client = $this->makeClient();
        $path = 'products/2026/09/abc123def.webp';

        $key = $client->buildObjectKey($path);

        $this->assertSame('ioms/products/2026/09/abc123def.webp', $key);
    }

    public function testBuildObjectKeyStripsLeadingSlashFromPath(): void
    {
        $client = $this->makeClient();

        $key = $client->buildObjectKey('/sales-orders/123/attachment.pdf');

        $this->assertSame('ioms/sales-orders/123/attachment.pdf', $key);
    }

    public function testBuildObjectKeyHandlesPlainFilename(): void
    {
        $client = $this->makeClient();

        $key = $client->buildObjectKey('document.pdf');

        $this->assertSame('ioms/document.pdf', $key);
    }

    // ─────────────────────────────────────────────────────────────────
    // extractObjectKey() — current ioms/ namespace
    // ─────────────────────────────────────────────────────────────────

    public function testExtractObjectKeyFromIomsUrlReturnsKey(): void
    {
        $client = $this->makeClient();
        $storedUrl = 'http://localhost:9000/portfolio-uploads/ioms/products/2026/09/abc123.webp';

        $key = $client->extractObjectKey($storedUrl);

        $this->assertSame('products/2026/09/abc123.webp', $key);
    }

    public function testExtractObjectKeyFromIomsUrlWithEncodedChars(): void
    {
        $client = $this->makeClient();
        // URL-encoded path with spaces and special chars
        $storedUrl = 'http://localhost:9000/portfolio-uploads/ioms/products/2026/09/invoice%20report%281%29.pdf';

        $key = $client->extractObjectKey($storedUrl);

        $this->assertSame('products/2026/09/invoice report(1).pdf', $key);
    }

    // ─────────────────────────────────────────────────────────────────
    // extractObjectKey() — backward compat for legacy products/ URLs
    // ─────────────────────────────────────────────────────────────────

    public function testExtractObjectKeyFromLegacyProductsUrlReturnsNull(): void
    {
        $client = $this->makeClient();
        // URL from before the ioms/ namespace migration
        $storedUrl = 'http://localhost:9000/portfolio-uploads/products/2026/09/oldabc.webp';

        $key = $client->extractObjectKey($storedUrl);

        // Legacy URLs → null (no-op delete, see buildObjectKey() comment)
        $this->assertNull($key);
    }

    public function testExtractObjectKeyFromUnrelatedUrlReturnsNull(): void
    {
        $client = $this->makeClient();

        $key = $client->extractObjectKey('http://example.com/some/other/path/file.pdf');

        $this->assertNull($key);
    }

    public function testExtractObjectKeyFromEmptyStringReturnsNull(): void
    {
        $client = $this->makeClient();

        $key = $client->extractObjectKey('');

        $this->assertNull($key);
    }

    // ─────────────────────────────────────────────────────────────────
    // publicUrl()
    // ─────────────────────────────────────────────────────────────────

    public function testPublicUrlFormatsCorrectly(): void
    {
        $client = $this->makeClient();

        $url = $client->publicUrl('ioms/products/2026/09/abc.webp');

        $this->assertSame('http://localhost:9000/portfolio-uploads/ioms/products/2026/09/abc.webp', $url);
    }

    public function testPublicUrlEncodesPathSegments(): void
    {
        $client = $this->makeClient();

        $url = $client->publicUrl('ioms/sales-orders/123/document.pdf');

        $this->assertSame(
            'http://localhost:9000/portfolio-uploads/ioms/sales-orders/123/document.pdf',
            $url
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // getPresignedUrl() — format validation (no network call)
    // ─────────────────────────────────────────────────────────────────

    public function testGetPresignedUrlContainsSignature(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 3600);

        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }

    public function testGetPresignedUrlContainsExpiration(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 7200);

        $this->assertStringContainsString('X-Amz-Expires=7200', $url);
    }

    public function testGetPresignedUrlUsesAlgorithmAws4HmacSha256(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 3600);

        $this->assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
    }

    public function testGetPresignedUrlContainsCredentialScope(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 3600);

        $this->assertStringContainsString('X-Amz-Credential=', $url);
        $this->assertStringContainsString('us-east-1', $url);
    }

    public function testGetPresignedUrlClampsExpirationToMinimum60Seconds(): void
    {
        $client = $this->makeClient();

        // Request with 30 seconds — should be clamped to minimum 60
        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 30);

        $this->assertStringContainsString('X-Amz-Expires=60', $url);
    }

    public function testGetPresignedUrlClampsExpirationToMaximum7Days(): void
    {
        $client = $this->makeClient();

        // Request with 1 million seconds — should be clamped to maximum 604800 (7 days)
        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 1000000);

        $this->assertStringContainsString('X-Amz-Expires=604800', $url);
    }

    public function testGetPresignedUrlUsesDefault3600Seconds(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp');

        $this->assertStringContainsString('X-Amz-Expires=3600', $url);
    }

    public function testGetPresignedUrlContainsSignedHostHeader(): void
    {
        $client = $this->makeClient();

        $url = $client->getPresignedUrl('ioms/products/2026/09/abc.webp', 3600);

        $this->assertStringContainsString('X-Amz-SignedHeaders=host', $url);
    }
}
