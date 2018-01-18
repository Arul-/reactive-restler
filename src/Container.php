<?php namespace Luracast\Restler;

use Exception;
use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Exceptions\ContainerException;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Exceptions\NotFoundException;
use Luracast\Restler\Utils\ClassName;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionParameter;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    private $instances;
    /**
     * @var array
     */
    private $aliases;
    /**
     * @var array
     */
    private $abstractAliases;
    /**
     * @var array
     */
    private $config;

    public function __construct(array $aliases = [], array $abstractAliases = [], &$config = [])
    {
        $this->instances = [];
        $this->aliases = $aliases;
        $this->abstractAliases = $abstractAliases;
        $this->config = $config;
    }

    public function setAliases(array $aliases, bool $clear = false)
    {
        $this->aliases = $clear ? $aliases : $aliases + $this->aliases;
    }

    public function setAbstractAliases(array $abstractAliases, bool $clear = false)
    {
        $this->abstractAliases = $clear ? $abstractAliases : $abstractAliases + $this->abstractAliases;
    }

    public function setConfig(&$config)
    {
        $this->config = &$config;
    }

    public function make($abstract, array $parameters = [])
    {
        if ($instance = $this->instances[$abstract] ?? false) {
            return $instance;
        }
        try {
            $class = ClassName::get($abstract);
        } catch (HttpException $e) {
        }
        if ($class && $instance = $this->instances[$class] ?? false) {
            return $instance;
        }
        $instance = $this->resolve($class ?? $abstract);
        $this->instances[$abstract] = $instance;
        if ($class) {
            $this->instances[$class] = $instance;
        }
        return $instance;
    }

    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
        try {
            if ($class = ClassName::get($abstract)) {
                $this->instances[$class] = $instance;
            }
        } catch (HttpException $e) {
        }
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        try {
            if ($instance = $this->instances[$id] ?? $this->instances[ClassName::get($id)] ?? false) {
                return $instance;
            }
        } catch (\Throwable $t) {
            throw new ContainerException('Error while retrieving the entry `' . $id . '`');
        }
        throw new NotFoundException(' No entry was found for `' . $id . '`` identifier');
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        if (isset($this->instances[$id])) {
            return true;
        }
        try {
            $class = ClassName::get($id);
        } catch (\Throwable $t) {
            return false;
        }
        return isset($this->instances[$class]);
    }

    /**
     * Build an instance of the given class
     *
     * @param string $class
     * @return mixed
     *
     * @throws Exception
     */
    public function resolve($class)
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("[$class] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->getDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Build up a list of dependencies for a given methods parameters
     *
     * @param array $parameters
     * @return array
     * @throws Exception
     */
    public function getDependencies($parameters)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();

            if (is_null($dependency)) {
                $dependencies[] = $this->resolvePrimitive($parameter);
            } else {
                $dependencies[] = $this->resolve($dependency->name);
            }
        }

        return $dependencies;
    }

    /**
     * @param ReflectionParameter $parameter
     * @return mixed|null|string
     * @throws Exception
     */
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if ($parameter->isArray()) {
            if ($value = $this->config[$parameter->name] ?? false) {
                return $value;
            }
            $class = ucfirst($parameter->name);
            if (class_exists($class) || $class = $this->aliases[$class] ?? false) {
                $value = $this->config[$parameter->name] = get_class_vars($class);
                return $value;
            }
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $this->unresolvablePrimitive($parameter);
    }

    /**
     * Throw an exception for an unresolvable primitive.
     *
     * @param  \ReflectionParameter $parameter
     * @return void
     *
     * @throws Exception
     */
    protected function unresolvablePrimitive(ReflectionParameter $parameter)
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new ContainerException($message);
    }
}