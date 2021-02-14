<?php

use Luracast\Restler\Defaults;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Upload;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;


define('BASE', __DIR__ . '/../..');
require BASE . '/vendor/autoload.php';

Defaults::$cacheDirectory = BASE . '/api/common/store';

Router::setOverridingRequestMediaTypes(Upload::class);
Router::setOverridingResponseMediaTypes(Html::class);
Router::mapApiClasses([
    'helper/functions' => Functions::class,
    'param/minmax' => MinMax::class,
    'param/minmaxfix' => MinMaxFix::class,
    'param/header' => Header::class,
    'param/type' => Type::class,
    'param/validation' => Validation::class,
    'request_data' => Data::class,
    'upload/files' => Files::class,
    'storage/cache' => CacheTest::class,
    'storage/session' => SessionTest::class,
    'overrides/method' => Method::class,
    'overrides/property' => Property::class,
]);

(new Restler())->handle();
