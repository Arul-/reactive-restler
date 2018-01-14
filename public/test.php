<?php declare(strict_types=1);

use Luracast\Restler\App;
use Luracast\Restler\Core;
use Luracast\Restler\Reactler;
use Luracast\Restler\Router;

include __DIR__ . "/../vendor/autoload.php";

App::$apiVendor = 'SomeVendor';
Router::setApiVersion(2);
Router::$formatMap = [
    'default' => 'Luracast\\Restler\\MediaTypes\\Json',
    'json' => 'Luracast\\Restler\\MediaTypes\\Json',
    'application/json' => 'Luracast\\Restler\\MediaTypes\\Json',
    'xml' => 'Luracast\\Restler\\MediaTypes\\Xml',
    'application/xml' => 'Luracast\\Restler\\MediaTypes\\Xml',
    'extensions' =>
        [
            0 => '.json',
            1 => '.xml',
        ],
];

$h = new Reactler();

$m = new ReflectionMethod($h, 'negotiateResponseMediaType');

$m->setAccessible(true);

/*
var_dump($m->invoke($h, 'http://localhost:8080/v2/examples/_011_versioning/bmi?height=190cm',
    'application/vnd.SomeVendor-v2+json'));
*/
var_dump($m->invoke($h, 'http://localhost:8080/examples/_003_multiformat/bmi.xml',
    ''));