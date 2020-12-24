<?php


use Luracast\Restler\Filters\RateLimiter;
use ratelimited\Authors;
use Luracast\Restler\Defaults;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;

define('BASE', __DIR__ . '/../../..');
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = BASE . '/api/common/store';
Defaults::$implementations[DataProviderInterface::class] = [SerializedFileDataProvider::class];

RateLimiter::setLimit('hour', 10);
Router::setFilters(RateLimiter::class);
Router::addAuthenticator(KeyAuth::class);
Router::mapApiClasses([
    Authors::class,
    Explorer::class
]);

(new Restler())->handle();
