<?php

use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\Core;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param string|null $make
     * @param array $parameters
     * @return mixed|ContainerInterface
     */
    function app($make = null, array $parameters = [])
    {
        /** @var ContainerInterface $container */
        static $container = null;
        if (is_null($make)) {
            return $container;
        }
        if (ContainerInterface::class === $make) {
            $container = new (ClassName::get(ContainerInterface::class))(...$parameters);
            return $container;
        }
        if (!$container) {
            return null;
        }
        return $container->make($make, $parameters);
    }
}

if (!function_exists('nested')) {
    /**
     * Get the value deeply nested inside an array / object
     *
     * Using isset() to test the presence of nested value can give a false positive
     *
     * This method serves that need
     *
     * When the deeply nested property is found its value is returned, otherwise
     * null is returned.
     * @param array|object $from array to extract the value from
     * @param string|array $key ... pass more to go deeply inside the array
     *                              alternatively you can pass a single array
     * @return mixed|null
     */
    function nested($from, $key/**, $key2 ... $key`n` */)
    {
        if (is_array($key)) {
            $keys = $key;
        } else {
            $keys = func_get_args();
            array_shift($keys);
        }
        foreach ($keys as $key) {
            if (is_array($from) && isset($from[$key])) {
                $from = $from[$key];
                continue;
            } elseif (is_object($from) && isset($from->{$key})) {
                $from = $from->{$key};
                continue;
            }
            return null;
        }
        return $from;
    }
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return ServerRequestInterface|array|mixed
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return app(ServerRequestInterface::class);
        }
        /** @var Core $core */
        if (!$core = app(Core::class)) {
            return $default;
        }
        $data = $core->getRequestData() ?? [];
        if (is_array($key)) {
            $values = [];
            foreach ($key as $k) {
                $values[$k] = nested($data, explode('.', $k));
            }
            return $values ?? $default;
        }
        return nested($data, explode('.', $key)) ?? $default;
    }
}

if (!function_exists('user')) {
    function user(): ?UserIdentificationInterface
    {
        return app(UserIdentificationInterface::class);
    }
}
