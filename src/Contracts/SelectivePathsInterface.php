<?php namespace Luracast\Restler\Contracts;


interface SelectivePathsInterface
{
    /**
     * Paths to be included in the process
     * @param string[] ...$included
     * @return void
     * @private
     */
    static function setIncludedPaths(string ...$included): void;

    /**
     * Paths to be excluded from the process
     * @param string[] ...$excluded
     * @return void
     * @private
     */
    static function setExcludedPaths(string ...$excluded): void;

    /**
     * @return array
     * @private
     */
    static function getIncludedPaths(): array;

    /**
     * @return array
     * @private
     */
    static function getExcludedPaths(): array;

    static function isPathSelected(string $path): bool;
}