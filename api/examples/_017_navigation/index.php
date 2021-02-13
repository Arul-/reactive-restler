<?php


use Luracast\Restler\Defaults;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\Middleware\SessionMiddleware;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";
Defaults::$cacheDirectory = BASE . '/api/common/store';
Html::$template = 'php'; //'handlebar'; //'twig'; //'blade';
Restler::$middleware[] = new SessionMiddleware();
Router::setResponseMediaTypes(
    Html::class
);
Router::mapApiClasses([
    '' => Website::class,
    Products::class
]);

(new Restler())->handle();
