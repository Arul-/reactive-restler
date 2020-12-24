<?php


use Luracast\Restler\Defaults;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\Middleware\SessionMiddleware;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = BASE . '/api/common/store';
Html::$template = 'blade'; //'handlebar'; //'twig'; //'php';
Restler::$middleware[] = new SessionMiddleware();
Router::setOverridingResponseMediaTypes(
    Json::class,
    Html::class
);
Router::mapApiClasses([
    Users::class
]);

(new Restler())->handle();
