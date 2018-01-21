<?php declare(strict_types=1);

use Luracast\Restler\Utils\ValidationInfo as VI2;
use Luracast\Restler\Data\ValidationInfo as VI1;

use Luracast\Restler\Utils\CommentParser as CP2;
use Luracast\Restler\CommentParser as CP1;

include __DIR__ . "/../vendor/autoload.php";

$data = ['name' => 'date', 'type' => 'string', 'properties' => ['type' => 'date']];

//var_export(new ValidationInfo($data));
//var_export(new OldVI($data));

$comment = '/**
     * Date validation
     *
     * @param string $date {@from body}{@type date}
     *
     * @return string {@type date}
     */';

$parsed = CP1::parse($comment);

$info1 = VI1::__set_state($parsed['param'][0]);
$info2 = VI2::__set_state($parsed['param'][0]);



var_export($parsed);