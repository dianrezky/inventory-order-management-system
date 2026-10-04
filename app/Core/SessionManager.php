<?php

namespace App\Core;

// Native PHP sessions backed by Redis: session_start() connects using the session.save_handler and session.save_path INI settings set in start() or in bootstrap.
class SessionManager
{
    private $name;
    private $lifetime;
    private $redisHost;
    private $redisPort;
    private $started = false;
    private $useFileSessions = false;

    public function __construct(
        $name = 'iom_session',
        $lifetime = 3600,
        $redisHost = 'redis',
        $redisPort = 6379
    ) {
        $this->name = $name;
        $this->lifetime = $lifetime;
        $this->redisHost = $redisHost;
        $this->redisPort = $redisPort;
    }

    public function useFileSessions()
    {
        // file-based sessions let unit tests run without a Redis dependency
        $this->useFileSessions = true;
    }

    public function start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!$this->useFileSessions && $this->isRedisReachable()) {
            ini_set('session.save_handler', 'redis');
            ini_set(
                'session.save_path',
                sprintf('tcp://%s:%d?database=0', $this->redisHost, $this->redisPort),
            );
        } elseif (!$this->useFileSessions) {
            // Redis unreachable (C-02): fall back to file-based sessions so login
            // still works instead of failing every request with an unusable session handler.
            error_log(sprintf(
                'SessionManager: Redis unreachable at %s:%d, falling back to file sessions',
                $this->redisHost,
                $this->redisPort,
            ));
            ini_set('session.save_handler', 'files');
        }

        session_name($this->name);

        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->started = true;
    }

    public function getLifetime()
    {
        return (int) $this->lifetime;
    }

    public function regenerate()
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            // called on login to prevent session fixation (BR-006); session data is kept, only the ID changes
            session_regenerate_id(true);
        }
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public function destroy()
    {
        $_SESSION = [];

        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
            session_unset();
            session_destroy();
        }
    }

    private function isRedisReachable()
    {
        // Short-timeout TCP probe (not a real Redis handshake) so a dead/unreachable
        // Redis host degrades gracefully instead of making every request hang or 500 (C-02).
        $connection = @fsockopen($this->redisHost, $this->redisPort, $errno, $errstr, 0.5);
        if ($connection === false) {
            return false;
        }

        fclose($connection);
        return true;
    }

    private function isHttps()
    {
        $env = $_SERVER['APP_ENV'] ?? 'local';
        if ($env === 'production') {
            return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? null) === '443';
        }

        return false;
    }
}
