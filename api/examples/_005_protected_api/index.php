<?php


use Luracast\Restler\Restler;
use Luracast\Restler\Router;

require __DIR__ . '/../../../vendor/autoload.php';

Router::addAuthenticator(SimpleAuth::class);
Router::mapApiClasses([
    Simple::class,
    Secured::class
]);

(new Restler())->handle();