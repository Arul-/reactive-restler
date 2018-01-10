<?php namespace Luracast\Restler;


use Closure;
use Illuminate\Container\Container as LaravelContainer;
use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Filters\RateLimiter;
use ReflectionParameter;

class Container extends LaravelContainer implements ContainerInterface
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $aliases = [], array $abstractAliases = [], &$config = [])
    {
        $this->aliases = $aliases;
        $this->abstractAliases = $abstractAliases;
        $this->config = $config;
    }

    public function has($id)
    {
        return parent::has($id);
    }

    /**
     * @param string $id
     * @return mixed
     * @throws \Illuminate\Container\EntryNotFoundException
     */
    public function get($id)
    {
        return parent::get($id);
    }

    /**
     * @param ReflectionParameter $parameter
     * @return mixed|null|string
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if (!is_null($concrete = $this->getContextualConcrete('$' . $parameter->name))) {
            return $concrete instanceof Closure ? $concrete($this) : $concrete;
        }
        $configClasses = [
            'defaults' => Defaults::class,
            'router' => Router::class,
            'ratelimiter' => RateLimiter::class,
            'passthrough' => PassThrough::class
        ];
        if ($parameter->isArray()) {
            if ($value = &$config[$parameter->name] ?? false) {
                return $value;
            } elseif ($class = $configClasses[$parameter->name] ?? false) {
                return $config[$parameter->name] = get_class_vars($class);
            }
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $this->unresolvablePrimitive($parameter);
    }

}