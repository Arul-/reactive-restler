<?php


use Luracast\Restler\Defaults;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\Middleware\SessionMiddleware;
use Luracast\Restler\Restler;
use Luracast\Restler\Routes;
use Luracast\Restler\UI\Forms;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = BASE . '/api/common/store';
Html::$template = 'blade'; //'handlebar'; //'twig'; //'php';
Restler::$middleware[] = new SessionMiddleware();
Routes::setFilters(Forms::class);
Routes::setOverridingResponseMediaTypes(
    Json::class,
    Html::class
);
Routes::mapApiClasses([
    Users::class
]);

(new Restler())->handle();
