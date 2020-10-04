<?php


use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

require __DIR__ . '/../../../vendor/autoload.php';

Router::setOverridingResponseMediaTypes(Json::class, Xml::class);

Router::mapApiClasses([
    BMI::class
]);

(new Restler())->handle();