<?php

namespace App\Core;

// Memcached wrapper that degrades gracefully to a no-op when Memcached is unavailable.
class CacheService
{
    private $host;
    private $port;
    private $defaultTtl;
    private $memcached = null;
    private $available = false;

    public function __construct($host = 'memcached', $port = 11211, $defaultTtl = 3600)
    {
        $this->host = $host;
        $this->port = $port;
        $this->defaultTtl = $defaultTtl;
        $this->connect();
    }

    public function get($key, $default = null)
    {
        if (!$this->available || $this->memcached === null) {
            return $default;
        }

        $value = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value !== false ? $value : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!$this->available || $this->memcached === null) {
            return;
        }

        $this->memcached->set($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function delete($key)
    {
        if (!$this->available || $this->memcached === null) {
            return;
        }

        $this->memcached->delete($key);
    }

    public function flushByPrefix($prefix)
    {
        // Memcached has no native prefix deletion, so a full flush is used; rare, e.g. after a locale switch
        if ($prefix === 'translations' && $this->available) {
            $this->memcached->flush();
        }
    }

    public function isAvailable()
    {
        return $this->available;
    }

    private function connect()
    {
        if (!extension_loaded('memcached')) {
            return;
        }

        try {
            $this->memcached = new \Memcached();
            $this->memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 500);
            $this->memcached->setOption(\Memcached::OPT_SEND_TIMEOUT, 500);
            $this->memcached->setOption(\Memcached::OPT_RECV_TIMEOUT, 500);

            // @ suppresses the notice raised when libmemcached lacks JSON support, which would otherwise pollute the HTTP response body
            $serializerOk = @($this->memcached->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_JSON) !== false);
            if (!$serializerOk) {
                @$this->memcached->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_PHP);
            }
            $this->memcached->addServer($this->host, $this->port);

            // trigger a connection check
            $this->memcached->getStats();
            $resultCode = $this->memcached->getResultCode();
            // Only RES_SUCCESS means the server actually answered. The earlier
            // `|| ($rc !== RES_SERVER_MARKED_DEAD)` collapsed to "true for any
            // code except DEAD", so an unreachable server (connect timeout, host
            // lookup failure, etc.) was reported available — every set() then
            // paid a network round-trip that silently failed.
            $this->available = ($resultCode === \Memcached::RES_SUCCESS);
        } catch (\Throwable $e) {
            $this->available = false;
        }
    }
}
