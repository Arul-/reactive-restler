<?php declare(strict_types=1);

use Luracast\Restler\Data\Route;
use Luracast\Restler\Data\Param;

include __DIR__ . "/../vendor/autoload.php";


$route = new Route();

$action = function (int $a, int $b) {
    return $a / $b;
};

$route->action = $action;

$a = new Param([]);
$a->type = 'int';
$a->name = 'a';
$route->addParameter($a);

$b = new Param([]);
$b->type = 'int';
$b->name = 'b';
$route->addParameter($b);

print_r($route->call([4, 2]));
echo PHP_EOL;

print_r($route->call(['b' => 4, 'a' => 2]));
echo PHP_EOL;

print_r($route->call([4, 'b' => 2]));
echo PHP_EOL;
