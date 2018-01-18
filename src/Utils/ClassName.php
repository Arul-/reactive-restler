<?php namespace Luracast\Restler\Utils;


use Luracast\Restler\App;
use Luracast\Restler\Exceptions\HttpException;

class ClassName
{

    const NAMESPACE_SEPARATOR = '\\';

    /**
     * Build versioned class name
     * @param string $name
     * @param string $namespace
     * @param int $version
     * @param bool $embed
     * @return string
     */
    public static function build(string $name, string $namespace, int $version, bool $embed = false)
    {
        $versionString = $version > 1 || $embed ? "v$version" : '';
        return $namespace . self::NAMESPACE_SEPARATOR . $versionString . self::NAMESPACE_SEPARATOR . $name;
    }

    /**
     * Parse version information from class name
     * @param $class
     * @param string|null $option
     * @return array
     */
    public static function parse($class, ?string $option = null)
    {
        $parts = explode(self::NAMESPACE_SEPARATOR, strrev($class), 3);
        $name = strrev($parts[0]);
        $count = count($parts);
        $version_found = false;
        if ($count > 1 && substr($parts[1], -1) == 'v' &&
            is_numeric($version = substr($parts[1], 0, -1)) &&
            $version = intval(strrev($version)) > 0
        ) {
            $version_found = true;
        } else {
            $version = 1;
        }
        $namespace = $count > 2 ? strrev($parts[2]) : '';
        $result = compact('name', 'namespace', 'version', 'version_found');
        if (is_null($option)) {
            return $result;
        } else {
            return $result[$option] ?? null;
        }
    }

    /**
     * Extract base class name
     *
     * @param $name
     * @return mixed
     */
    public static function short($name)
    {
        $name = explode('\\', $name);
        return end($name);
    }

    /**
     * Find valid class name from an abstract name
     *
     * @param string $abstract
     * @return string
     * @throws HttpException
     */
    public static function get(string $abstract)
    {
        $interface = App::$aliases[$abstract] ?? $abstract;
        if ($class = App::$implementations[$interface][0] ?? false) {
            if (interface_exists($interface) && class_implements($class)[$interface] ?? false) {
                return $class;
            }
            throw new HttpException(
                501,
                'App::$implementations should contain at least one valid implementation for ' . $interface
            );
        }
        if (class_exists($interface)) {
            return $interface;
        }
        throw new HttpException(
            501,
            'Could not find a class for ' . $interface
        );
    }

    /**
     * Get fully qualified class name for the given scope
     *
     * @param string $name
     * @param array $scope local scope
     *
     * @return string|boolean returns the class name or false
     */
    public static function resolve(string $name, array $scope)
    {
        if (empty($name) || !is_string($name)) {
            return false;
        }

        if (Type::isPrimitive($name)) {
            return false;
        }

        $divider = '\\';
        $qualified = false;
        if ($name{0} == $divider) {
            $qualified = trim($name, $divider);
        } elseif (array_key_exists($name, $scope)) {
            $qualified = $scope[$name];
        } else {
            $qualified = $scope['*'] . $name;
        }
        if (class_exists($qualified)) {
            return $qualified;
        }
        if (isset(App::$aliases[$name])) {
            $qualified = App::$aliases[$name];
            if (class_exists($qualified)) {
                return $qualified;
            }
        }
        return false;
    }
}