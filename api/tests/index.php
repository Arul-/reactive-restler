<?php

use Luracast\Restler\Restler;
use Luracast\Restler\Router;

require __DIR__ . '/../../vendor/autoload.php';

Router::mapApiClasses([
    'param/minmax' => MinMax::class,
    'param/minmaxfix' => MinMaxFix::class,
    'param/type' => Type::class,
    'param/validation' => Validation::class,
    'request_data' => Data::class,
    'upload/files' => Files::class,
    'storage/cache' => CacheTest::class,
    'storage/session' => SessionTest::class,
]);

(new Restler())->handle();
