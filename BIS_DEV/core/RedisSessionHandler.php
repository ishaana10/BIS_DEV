<?php

class NuRedisSessionHandler implements SessionHandlerInterface
{
    private $redis;
    private $prefix;
    private $ttl;

    public function __construct($redis, $prefix = 'nuvis:sess:', $ttl = 1800)
    {
        $this->redis  = $redis;
        $this->prefix = $prefix;
        $this->ttl    = $ttl > 0 ? (int)$ttl : 1800;
    }

    public function open($path, $name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        try {
            $data = $this->redis->get($this->prefix . $id);
            return ($data === false || $data === null) ? '' : (string)$data;
        } catch (\Throwable $e) {
            error_log('[NuRedisSessionHandler read] ' . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data)
    {
        try {
            return (bool)$this->redis->setex($this->prefix . $id, $this->ttl, $data);
        } catch (\Throwable $e) {
            error_log('[NuRedisSessionHandler write] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy($id)
    {
        try {
            return (bool)$this->redis->del($this->prefix . $id);
        } catch (\Throwable $e) {
            error_log('[NuRedisSessionHandler destroy] ' . $e->getMessage());
            return false;
        }
    }

    public function gc($max_lifetime)
    {
        return 0;
    }
}
