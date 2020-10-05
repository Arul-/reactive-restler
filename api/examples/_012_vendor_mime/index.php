<?php


use Luracast\Restler\Defaults;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use SomeVendor\v1\BMI;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$apiVendor = "SomeVendor";
Defaults::$useVendorMIMEVersioning = true;

Router::setApiVersion(2);
Router::mapApiClasses([
    'bmi' => BMI::class,
    Explorer::class
]);

(new Restler())->handle();