<?php namespace Luracast\Restler\Contracts;


trait SelectivePathsTrait
{
    /**
     * @var array paths where rate limit has to be applied
     */
    private static $includedPaths = [''];

    /**
     * @var array all paths beginning with any of the following will be excluded
     * from rate limiting
     */
    private static $excludedPaths = [];

    public static function getIncludedPaths(): array
    {
        return static::$includedPaths;
    }

    public static function getExcludedPaths(): array
    {
        return static::$excludedPaths;
    }

    static function setIncludedPaths(string ...$included): void
    {
        static::$includedPaths = $included;
    }

    static function setExcludedPaths(string ...$excluded): void
    {
        static::$includedPaths = $excluded;
    }
}