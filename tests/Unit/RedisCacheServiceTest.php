<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\RedisCacheService;
use PHPUnit\Framework\TestCase;

final class RedisCacheServiceTest extends TestCase
{
    public function testUnreachableRedisDegradesToNoOp(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('phpredis extension not loaded');
        }

        $cache = new RedisCacheService('127.0.0.1', 1, 1);

        self::assertFalse($cache->isAvailable());
        $cache->set('k', ['a' => 1]);
        $cache->delete('k');
        self::assertSame('fallback', $cache->get('k', 'fallback'));
    }

    public function testRoundTripAndDeleteAgainstLiveRedis(): void
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $cache = new RedisCacheService($host, $port, 1);

        if (!$cache->isAvailable()) {
            self::markTestSkipped('Redis not reachable');
        }

        $grouped = ['reports.stock_ledger.view' => ['Admin', 'WarehouseStaff']];
        $key = 'test.role_permissions.grouped.' . uniqid();

        self::assertNull($cache->get($key));
        $cache->set($key, $grouped, 60);
        self::assertSame($grouped, $cache->get($key));

        $cache->delete($key);
        self::assertNull($cache->get($key));
    }
}
