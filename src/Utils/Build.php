<?php namespace Luracast\Restler\Utils;


class Build
{
    public static function namespace(string $name, string $namespace, int $version, bool $embed = false)
    {
        $versionString = $version > 1 || $embed ? "v$version" : '';
        return $namespace . Parse::NAMESPACE_SEPARATOR . $versionString . Parse::NAMESPACE_SEPARATOR . $name;
    }
}