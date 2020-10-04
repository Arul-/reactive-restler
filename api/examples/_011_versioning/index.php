<?php


use Luracast\Restler\Defaults;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use v1\BodyMassIndex;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$useUrlBasedVersioning = true;

Router::setApiVersion(2);
Router::mapApiClasses([
    BodyMassIndex::class,
    Explorer::class
]);

(new Restler())->handle();