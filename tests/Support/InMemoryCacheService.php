<?php

namespace Tests\Support;

use App\Core\CacheService;

// Models cache hits, expiry, deletion and unavailable fallback without sockets.
class InMemoryCacheService extends CacheService
{
    private $values = [];
    private $available;

    public function __construct($available = true)
    {
        $this->available = $available;
    }

    public function get($key, $default = null)
    {
        $entry = $this->values[$key] ?? null;
        if (!$this->available || $entry === null || ($entry['expires'] !== null && $entry['expires'] <= time())) {
            return $default;
        }

        return $entry['value'];
    }

    public function set($key, $value, $ttl = null)
    {
        if ($this->available) {
            $this->values[$key] = ['value' => $value, 'expires' => $ttl === 0 ? null : time() + ($ttl ?? 3600)];
        }
    }

    public function delete($key)
    {
        unset($this->values[$key]);
    }

    public function flushByPrefix($prefix)
    {
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->values[$key]);
            }
        }
    }

    public function isAvailable()
    {
        return $this->available;
    }
}
