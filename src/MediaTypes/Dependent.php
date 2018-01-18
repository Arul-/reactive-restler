<?php namespace Luracast\Restler\MediaTypes;

use Luracast\Restler\Exceptions\HttpException;

abstract class Dependent extends MediaType
{
    /**
     * @return array {@type associative}
     *               CLASS_NAME => vendor/project:version
     */
    abstract public function dependencies();

    protected function checkDependencies()
    {
        foreach ($this->dependencies() as $className => $package) {
            if (!class_exists($className, true)) {
                throw new HttpException(
                    500,
                    get_called_class() . ' has external dependency. Please run `composer require ' .
                    $package . '` from the project root. Read https://getcomposer.org for more info'
                );
            }
        }
    }

    public function __construct()
    {
        $this->checkDependencies();
    }
}