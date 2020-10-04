<?php


use improved\Authors;
use Luracast\Restler\Defaults;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = BASE . '/api/common/store';
Defaults::$implementations[DataProviderInterface::class] = [SerializedFileDataProvider::class];

Router::mapApiClasses([
    Authors::class,
    Explorer::class
]);

(new Restler())->handle();