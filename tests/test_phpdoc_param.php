<?php declare(strict_types=1);


use Luracast\Restler\Router;

include __DIR__ . "/../vendor/autoload.php";


class Api
{
    /**
     * @param int $age
     */
    public function get($id, $name, $age)
    {
        return func_get_args();
    }
}

Router::addAPI('Api');

print_r(Router::toArray());