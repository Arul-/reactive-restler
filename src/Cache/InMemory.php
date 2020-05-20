<?php


namespace Luracast\Restler\Cache;


class InMemory extends Base
{
    private $store = [];

    public function get($key, $default = null)
    {
        return $this->store[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $this->store[$key] = $value;
    }

    public function delete($key)
    {
        unset($this->store[$key]);
    }

    public function clear()
    {
        $this->store = [];
    }

    public function has($key)
    {
        return array_key_exists($key, $this->store);
    }
}
