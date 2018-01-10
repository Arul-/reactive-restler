<?php namespace Luracast\Restler;


use Closure;
use Illuminate\Container\Container as LaravelContainer;
use Luracast\Restler\Filters\RateLimiter;
use Psr\Container\ContainerInterface;
use ReflectionParameter;

class Container extends LaravelContainer implements ContainerInterface
{
    public function __construct(array $aliases = [], array $abstractAliases = [])
    {
        $this->aliases = $aliases;
        $this->abstractAliases = $abstractAliases;
    }

    public function has($id)
    {
        return parent::has($id);
    }

    public function get($id)
    {
        return parent::get($id);
    }

    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if (!is_null($concrete = $this->getContextualConcrete('$' . $parameter->name))) {
            return $concrete instanceof Closure ? $concrete($this) : $concrete;
        }

        if ($parameter->getType()->getName() == 'array') {
            
            $configClasses = [
                'defaults' => Defaults::class,
                'router' => Router::class,
                'ratelimiter' => RateLimiter::class,
                'passthrough'=> PassThrough::class
            ];
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $this->unresolvablePrimitive($parameter);
    }

}