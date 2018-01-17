<?php namespace Luracast\Restler\Utils;


class Version
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
}