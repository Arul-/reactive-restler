<?php

use Luracast\Restler\Restler;
use Luracast\Restler\Router;

require __DIR__ . '/../../../vendor/autoload.php';

Router::mapApiClasses([
    '' => Storage::class
]);

(new Restler())->handle();
