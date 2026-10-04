<?php

namespace App\Core;

use RuntimeException;

// Minimal S3-compatible client for MinIO — implements just enough of the AWS
// Signature Version 4 signing process to PUT, GET, and DELETE objects over curl.
// Deliberately hand-rolled instead of pulling in aws/aws-sdk-php: this
// project keeps its dependency footprint small (see composer.json — only
// vlucas/phpdotenv besides PHP extensions) so every piece of behavior stays
// something we wrote and can explain, rather than a few hundred KB of
// generated SDK code for the ~80 lines of signing logic we actually need.
//
// Reference: https://docs.aws.amazon.com/general/latest/gr/sigv4-signed-request-examples.html
// MinIO implements the same S3 REST + SigV4 contract as AWS S3, so this
// works unmodified against a MinIO server (only the endpoint differs).
//
// IOMS uses the shared portfolio-uploads bucket on the portfolio-apps MinIO
// instance. All IOMS objects use the "ioms/" namespace prefix to avoid
// colliding with other apps sharing the same bucket.
//
// Architecture:
//   IOMS → MinioClient → MinIO/AIStor → portfolio-uploads bucket
//                         ↓
//                    ioms/<resource>/<identifier>/<uuid>.<ext>
//
class MinioClient
{
    /** Namespace prefix for all IOMS objects inside the shared bucket. */
    public const IOMS_PREFIX = 'ioms/';

    private $endpoint;
    private $region;
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $publicBaseUrl;

    /**
     * @throws RuntimeException if any required configuration value is missing or empty.
     */
    public function __construct(
        string $endpoint,
        string $region,
        string $accessKey,
        string $secretKey,
        string $bucket,
        string $publicBaseUrl
    ) {
        if ($endpoint === '') {
            throw new RuntimeException('MinIO endpoint is not configured. Set MINIO_ENDPOINT.');
        }
        if ($accessKey === '') {
            throw new RuntimeException('MinIO access key is not configured. Set MINIO_ACCESS_KEY.');
        }
        if ($secretKey === '') {
            throw new RuntimeException('MinIO secret key is not configured. Set MINIO_SECRET_KEY.');
        }
        if ($bucket === '') {
            throw new RuntimeException('MinIO bucket is not configured. Set MINIO_BUCKET.');
        }

        $this->endpoint = rtrim($endpoint, '/');
        $this->region = $region;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->publicBaseUrl = rtrim($publicBaseUrl, '/');
    }

    // ─────────────────────────────────────────────────────────────────
    // WRITE OPERATIONS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Uploads $body under object key $key.
     *
     * The key MUST already include the "ioms/" namespace prefix.
     * Callers are responsible for prepending the prefix.
     *
     * @throws RuntimeException on any non-2xx response or transport failure.
     *                          Callers (ImageUploadService) already wrap this in a
     *                          try/catch(\Throwable) and translate it into a Result,
     *                          so we don't need Result plumbing here.
     */
    public function putObject(string $key, string $body, string $contentType): void
    {
        $response = $this->request('PUT', $key, $body, [
            'Content-Type' => $contentType,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(sprintf(
                'MinIO PUT %s failed (HTTP %d): %s',
                $key,
                $response['status'],
                $response['body']
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // READ OPERATIONS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns a publicly accessible URL for the given object key.
     * Use this only when the bucket is configured with public-read access.
     *
     * The key MUST already include the "ioms/" namespace prefix.
     *
     * @see getPresignedUrl() for time-limited private access.
     */
    public function publicUrl(string $key): string
    {
        return $this->publicBaseUrl . '/' . $this->bucket . '/' . $this->encodeKeyPath($key);
    }

    /**
     * Generates a presigned (pre-signed) GET URL valid for $expiresInSeconds.
     *
     * Use this for objects that should NOT be publicly accessible — e.g.
     * attachments, documents, or any file whose access must be gated by IOMS
     * authorization rather than bucket-level public-read.
     *
     * The URL contains an AWS SigV4 signature valid for the requested
     * duration; after expiry the URL returns 403.
     *
     * The key MUST already include the "ioms/" namespace prefix.
     *
     * @param string $key  The object key (with ioms/ prefix).
     * @param int    $expiresInSeconds  URL validity duration (min 60, max 604800).
     * @throws RuntimeException on signing failure.
     */
    public function getPresignedUrl(string $key, int $expiresInSeconds = 3600): string
    {
        $expiresInSeconds = max(60, min(604800, $expiresInSeconds));
        $expirationIso = gmdate('Y-m-d\TH:i:s\Z', time() + $expiresInSeconds);

        // Presigned URL query parameters that MUST be included in the signed headers
        $queryParams = [
            'X-Amz-Algorithm'    => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => rawurlencode($this->accessKey) . '%2F' . rawurlencode(
                gmdate('Ymd') . '%2F' . rawurlencode($this->region) . '%2Fs3%2Faws4_request'
            ),
            'X-Amz-Date'         => gmdate('Ymd\THis\Z'),
            'X-Amz-Expires'      => (string) $expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($queryParams);

        $signedHeaders = 'host';
        $encodedKey = $this->encodeKeyPath($key);

        // ── Build the canonical request for presigned URL ───────────────
        $canonicalUri = '/' . $this->bucket . '/' . $encodedKey;
        $canonicalQueryString = '';
        foreach ($queryParams as $name => $value) {
            $canonicalQueryString .= rawurlencode($name) . '=' . $value . '&';
        }
        $canonicalQueryString = rtrim($canonicalQueryString, '&');

        $hostHeader = parse_url($this->endpoint, PHP_URL_HOST) . $this->portSuffix();
        $payloadHash = hash('sha256', ''); // Empty payload for GET

        $canonicalHeaders = 'host:' . $hostHeader . "\n";
        $signedHeaders = 'host';

        $canonicalRequest = implode("\n", [
            'GET',
            $canonicalUri,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $dateStamp = gmdate('Ymd');
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            gmdate('Ymd\THis\Z'),
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        // Build the final presigned URL
        $presignedUrl = $this->endpoint
            . $canonicalUri
            . '?' . $canonicalQueryString
            . '&X-Amz-Signature=' . rawurlencode($signature);

        return $presignedUrl;
    }

    /**
     * Tests whether the given object key exists by issuing a HEAD request.
     * Returns true if the server responds HTTP 200; false for 404; throws
     * RuntimeException for any other non-2xx response.
     *
     * The key MUST already include the "ioms/" namespace prefix.
     *
     * @throws RuntimeException on transport errors or non-404 server errors.
     */
    public function objectExists(string $key): bool
    {
        $response = $this->request('HEAD', $key, '', []);

        if ($response['status'] === 404) {
            return false;
        }
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return true;
        }

        throw new RuntimeException(sprintf(
            'MinIO HEAD %s failed (HTTP %d): %s',
            $key,
            $response['status'],
            $response['body']
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE OPERATIONS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Best-effort delete — mirrors the @unlink(...) semantics this class
     * replaces in ImageUploadService::delete(): failures are swallowed
     * because a stray orphaned object is not worth failing a user action
     * over (the same trade-off the local-disk version already made).
     *
     * The key MUST already include the "ioms/" namespace prefix.
     */
    public function deleteObject(string $key): void
    {
        try {
            $this->request('DELETE', $key, '', []);
        } catch (\Throwable $e) {
            error_log('MinioClient::deleteObject(' . $key . ') failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * IOMS namespace prefix — prepend this to every object key written by IOMS.
     * Callers should use this constant rather than hardcoding the string.
     */
    public function buildObjectKey(string $path): string
    {
        return self::IOMS_PREFIX . ltrim($path, '/');
    }

    /**
     * Reverses publicUrl() to recover the object key from a stored path.
     * Also handles legacy "products/" URLs that predate the ioms/ namespace
     * migration — those return null (no-op delete) so that old image_path
     * values stored in the database do not cause delete errors; the MinIO
     * server retains them until manually purged.
     *
     * @param string $storedPath  The URL previously stored in image_path.
     * @return string|null  The object key, or null if not a MinIO URL.
     */
    public function extractObjectKey(string $storedPath): ?string
    {
        // Build the current ioms/ pattern
        $iomsPattern = $this->publicBaseUrl . '/' . $this->bucket . '/' . self::IOMS_PREFIX;
        $legacyPattern = $this->publicBaseUrl . '/' . $this->bucket . '/products/';

        if (str_starts_with($storedPath, $iomsPattern)) {
            $key = substr($storedPath, strlen($iomsPattern));
            return $key !== '' ? rawurldecode($key) : null;
        }

        // Legacy "products/" URLs — return null so delete is a no-op
        // (orphaned objects are acceptable; delete errors are not)
        if (str_starts_with($storedPath, $legacyPattern)) {
            return null;
        }

        // Not one of our URLs — return null (no-op delete)
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: HTTP TRANSPORT
    // ─────────────────────────────────────────────────────────────────

    private function request(string $method, string $key, string $body, array $extraHeaders): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) . $this->portSuffix();
        $canonicalUri = '/' . $this->bucket . '/' . $this->encodeKeyPath($key);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = array_merge($extraHeaders, [
            'Host'               => $host,
            'X-Amz-Content-Sha256' => $payloadHash,
            'X-Amz-Date'          => $amzDate,
        ]);

        $authorization = $this->buildAuthorizationHeader(
            $method,
            $canonicalUri,
            $headers,
            $payloadHash,
            $amzDate,
            $dateStamp
        );

        $headers['Authorization'] = $authorization;

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init($this->endpoint . $canonicalUri);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER    => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS    => $method === 'PUT' ? $body : null,
            CURLOPT_TIMEOUT       => 15,
        ]);

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('MinIO request transport error: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $responseBody];
    }

    private function buildAuthorizationHeader(
        string $method,
        string $canonicalUri,
        array $headers,
        string $payloadHash,
        string $amzDate,
        string $dateStamp
    ): string {
        // SigV4 requires headers sorted (case-insensitively) by name, both
        // for the "canonicalHeaders" block and the "signedHeaders" list —
        // the server recomputes the signature the same way and rejects the
        // request if the two don't match byte-for-byte.
        $sortable = [];
        foreach ($headers as $name => $value) {
            $sortable[strtolower($name)] = trim($value);
        }
        ksort($sortable);

        $canonicalHeaders = '';
        foreach ($sortable as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }
        $signedHeadersList = implode(';', array_keys($sortable));

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '', // canonical query string — always empty for PUT/DELETE
            $canonicalHeaders,
            $signedHeadersList,
            $payloadHash,
        ]);

        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $credentialScope,
            $signedHeadersList,
            $signature
        );
    }

    private function deriveSigningKey(string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    // URI-encodes each path segment (per RFC 3986, matching AWS's
    // canonicalization rules) while keeping the "/" separators between path
    // segments literal.
    private function encodeKeyPath(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    }

    private function portSuffix(): string
    {
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME);
        $isDefaultPort = ($scheme === 'https' && $port === 443)
                      || ($scheme === 'http'  && $port === 80);

        return ($port !== null && !$isDefaultPort) ? ':' . $port : '';
    }
}
