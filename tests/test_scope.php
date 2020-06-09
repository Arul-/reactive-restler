<?php

declare(strict_types=1);


use Luracast\Restler\Core;
use Luracast\Restler\Router;

include __DIR__ . "/../vendor/autoload.php";

print_r(Router::scope(new ReflectionClass(Core::class)));
