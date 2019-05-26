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

    static function isPathSelected(string $path): bool
    {
        $notInPath = true;
        /** @var SelectivePathsInterface $class */
        foreach (static::getIncludedPaths() as $include) {
            if (empty($include) || 0 === strpos($path, $include)) {
                $notInPath = false;
                break;
            }
        }
        if ($notInPath) {
            return false;
        }
        foreach (static::getExcludedPaths() as $exclude) {
            if (empty($exclude) && empty($path)) {
                return false;
            } elseif (0 === strpos($path, $exclude)) {
                return false;
            }
        }
        return true;
    }
}