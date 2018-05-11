<?php

namespace Loopy\Spartan\Cache;


interface CacheStore
{
    public function put($key, $details);

    public function get($key);

    public function has($key);

    public function forget($key);

}