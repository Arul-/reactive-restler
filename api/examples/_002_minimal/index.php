<?php


use Luracast\Restler\Restler;
use Luracast\Restler\Router;

require __DIR__ . '/../../../vendor/autoload.php';

Router::mapApiClasses([
    Math::class
]);

(new Restler())->handle();