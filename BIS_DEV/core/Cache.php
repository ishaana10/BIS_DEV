<?php

/**
 * NuCache - Simple Redis-backed cache with graceful fallback
 * Compatible with PHP 7.3+
 */
class NuCache
{
    private static $instance = null;
    private $redis = null;
    private $enabled = false;
    private $prefix = 'nuvis:';
    private $defaultTtl = 300;

    private function __construct()
    {
        global $nuConfigRedis, $nuConfig;

        $cfg = isset($nuConfigRedis) ? $nuConfigRedis : (isset($nuConfig['redis']) ? $nuConfig['redis'] : []);

        if (empty($cfg['enabled'])) {
            return;
        }

        if (!extension_loaded('redis')) {
            error_log('[NuCache] phpredis extension not loaded');
            return;
        }

        try {
            $this->redis = new \Redis();

            $scheme = isset($cfg['scheme']) ? $cfg['scheme'] : 'unix';
            $timeout = isset($cfg['timeout']) ? (float)$cfg['timeout'] : 2.5;

            if ($scheme === 'unix') {
                $socketPath = isset($cfg['path']) ? $cfg['path'] : ((getenv('HOME') ?: '') . '/.redis/redis.sock');
                if (!file_exists($socketPath)) {
                    $homeDir = getenv('HOME');
                    if ($homeDir) {
                        @touch($homeDir . '/.redis.on');
                    }

                    if (!file_exists($socketPath)) {
                        return;
                    }
                }
                $connected = $this->redis->connect(
                    $socketPath,
                    0,
                    $timeout
                );
            } else {
                $host = isset($cfg['host']) ? $cfg['host'] : '127.0.0.1';
                $port = isset($cfg['port']) ? (int)$cfg['port'] : 6379;
                $connected = $this->redis->connect(
                    $host,
                    $port,
                    $timeout
                );
            }

            if (!$connected) {
                throw new \RuntimeException('Redis connect failed');
            }

            if (!empty($cfg['password'])) {
                $this->redis->auth($cfg['password']);
            }

            $database = isset($cfg['database']) ? (int)$cfg['database'] : 0;
            $this->redis->select($database);
            $this->prefix     = isset($cfg['prefix']) ? (string)$cfg['prefix'] : 'nuvis:';
            $this->defaultTtl = isset($cfg['default_ttl']) ? (int)$cfg['default_ttl'] : 300;
            $this->enabled    = true;

        } catch (\Throwable $e) {
            error_log('[NuCache] ' . $e->getMessage());
            $this->redis   = null;
            $this->enabled = false;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
    }

    public function isEnabled()
    {
        return $this->enabled && $this->redis !== null;
    }

    private function key($key)
    {
        return $this->prefix . $key;
    }

    public function get($key, $default = null)
    {
        if (!$this->isEnabled()) {
            return $default;
        }

        try {
            $value = $this->redis->get($this->key($key));
            if ($value === false) {
                return $default;
            }
            $decoded = @unserialize($value, ['allowed_classes' => ['stdClass']]);
            return ($decoded === false && $value !== serialize(false))
                ? $value
                : $decoded;
        } catch (\Throwable $e) {
            error_log('[NuCache get] ' . $e->getMessage());
            return $default;
        }
    }

    public function set($key, $value, $ttl = null)
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $ttl = $ttl !== null ? $ttl : $this->defaultTtl;
            $payload = serialize($value);
            if ($ttl > 0) {
                return $this->redis->setex($this->key($key), $ttl, $payload);
            }
            return $this->redis->set($this->key($key), $payload);
        } catch (\Throwable $e) {
            error_log('[NuCache set] ' . $e->getMessage());
            return false;
        }
    }

    public function delete($key)
    {
        if (!$this->isEnabled()) {
            return false;
        }
        try {
            return (bool)$this->redis->del($this->key($key));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete multiple keys by pattern (use carefully) */
    public function deleteByPattern($pattern)
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        try {
            $keys = $this->redis->keys($this->prefix . $pattern);
            if (empty($keys)) {
                return 0;
            }
            return (int)$this->redis->del($keys);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = call_user_func($callback);
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function flush()
    {
        if (!$this->isEnabled()) {
            return false;
        }
        try {
            $keys = $this->redis->keys($this->prefix . '*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Expose native Redis for advanced use */
    public function client()
    {
        return $this->isEnabled() ? $this->redis : null;
    }

    // ─── Invalidation Helpers ──────────────────────────────────────────────────

    public function invalidateForm($formId)
    {
        $this->delete("form:meta:{$formId}");
        $this->deleteByPattern("menu:*");
    }

    public function invalidateSystemSettings()
    {
        $this->deleteByPattern("sys:*");
    }

    public function invalidateUserMenu($userId)
    {
        $this->deleteByPattern("menu:user:{$userId}:*");
    }

    public function invalidateMenus()
    {
        $this->deleteByPattern("menu:*");
    }
}
