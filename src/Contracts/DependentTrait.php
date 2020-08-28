<?php


namespace Luracast\Restler\Contracts;


use Luracast\Restler\Exceptions\HttpException;

trait DependentTrait
{
    protected static function checkDependencies()
    {
        foreach (static::dependencies() as $className => $package) {
            if (!class_exists($className, true)) {
                throw new HttpException(
                    500,
                    get_called_class() . ' has external dependency. Please run `composer require ' .
                    $package . '` from the project root. Read https://getcomposer.org for more info'
                );
            }
        }
    }

    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    abstract public static function dependencies();
}
