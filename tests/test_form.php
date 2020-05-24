<?php

declare(strict_types=1);

use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Upload;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\UI\Forms;

include __DIR__ . "/../vendor/autoload.php";

Router::setOverridingRequestMediaTypes(Upload::class);
Router::setOverridingResponseMediaTypes(Json::class, Html::class);
Router::mapApiClasses(
    [
        'tests/upload/files' => Files::class,
        'examples/_016_forms/users' => Users::class,
    ]
);

$routes = Router::toArray();
$route = $routes['v1']['tests/upload/files']['GET'];

$restler = new Restler();
$f = new StaticProperties(Forms::class);
$form = new Forms($restler, $route, $f);
$form->get();
