<?php

namespace Loopy\Spartan\Cache;


class MemoryStore implements CacheStore
{

    protected $cache = [];

    public function put($key, $details)
    {
        $this->cache[$key] = $details;
    }

    public function get($key)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        return null;
    }

    public function has($key)
    {
        return isset($this->cache[$key]);
    }

    public function forget($key)
    {
        unset($this->cache[$key]);
    }
}