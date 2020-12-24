<?php


use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Router::addAuthenticator(AccessControl::class);

Router::mapApiClasses([
    '' => Access::class,
    Explorer::class
]);

(new Restler())->handle();