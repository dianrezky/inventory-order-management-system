<?php

namespace Tests\Support;

use App\Core\SessionManager;

// Unit-test session boundary: no cookies, global session state, files or Redis.
class InMemorySessionManager extends SessionManager
{
    private $values = [];

    public function __construct()
    {
    }

    public function start()
    {
    }

    public function regenerate()
    {
    }

    public function getLifetime()
    {
        return 3600;
    }

    public function get($key, $default = null)
    {
        return $this->values[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function has($key)
    {
        return isset($this->values[$key]);
    }

    public function remove($key)
    {
        unset($this->values[$key]);
    }

    public function destroy()
    {
        $this->values = [];
    }
}
