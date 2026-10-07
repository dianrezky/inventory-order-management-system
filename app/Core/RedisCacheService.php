<?php

namespace App\Core;

// Redis-backed read cache (phpredis) with the same get/set/delete contract as CacheService, degrading to a no-op when Redis is unavailable.
// Extends CacheService only so it is injectable wherever a CacheService is typed; it does not use Memcached and skips the parent constructor.
// Uses its own logical database, separate from the session database, so cache keys never mix with login state (ADR-005).
class RedisCacheService extends CacheService
{
    private const KEY_PREFIX = 'cache:';

    private $host;
    private $port;
    private $database;
    private $defaultTtl;
    private $redis = null;
    private $available = false;

    public function __construct($host = 'redis', $port = 6379, $database = 1, $defaultTtl = 3600)
    {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->defaultTtl = $defaultTtl;
        $this->connect();
    }

    public function get($key, $default = null)
    {
        if (!$this->available) {
            return $default;
        }

        try {
            $raw = $this->redis->get(self::KEY_PREFIX . $key);
        } catch (\Throwable $e) {
            $this->available = false;
            return $default;
        }

        if ($raw === false || $raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!$this->available) {
            return;
        }

        try {
            $this->redis->setex(self::KEY_PREFIX . $key, (int) ($ttl ?? $this->defaultTtl), json_encode($value));
        } catch (\Throwable $e) {
            $this->available = false;
        }
    }

    public function delete($key)
    {
        if (!$this->available) {
            return;
        }

        try {
            $this->redis->del(self::KEY_PREFIX . $key);
        } catch (\Throwable $e) {
            $this->available = false;
        }
    }

    public function flushByPrefix($prefix)
    {
        if (!$this->available) {
            return;
        }

        try {
            $iterator = null;
            while (($keys = $this->redis->scan($iterator, self::KEY_PREFIX . $prefix . '*', 100)) !== false) {
                if ($keys) {
                    $this->redis->del($keys);
                }
                if ($iterator === 0) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->available = false;
        }
    }

    public function isAvailable()
    {
        return $this->available;
    }

    private function connect()
    {
        if (!extension_loaded('redis')) {
            return;
        }

        try {
            $redis = new \Redis();
            // 0.5s connect/read timeout so a dead Redis cannot stall every request
            if (!$redis->connect($this->host, (int) $this->port, 0.5, null, 0, 0.5)) {
                return;
            }
            $redis->select((int) $this->database);
            $this->redis = $redis;
            $this->available = true;
        } catch (\Throwable $e) {
            $this->redis = null;
            $this->available = false;
        }
    }
}
