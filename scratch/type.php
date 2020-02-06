<?php declare(strict_types=1);

use Luracast\Restler\Data\Type;

include __DIR__ . "/../vendor/autoload.php";

$type = Type::__set_state(['type' => 'int', 'multiple' => true, 'scalar' => true]);

echo $type . PHP_EOL;

class Test
{
    public ?int $obj;
}

echo (Type::fromProp(new ReflectionProperty(Test::class, 'obj'))).PHP_EOL;
/*

$p = new ReflectionProperty(Type::class, 'format');

$t = $p->getType();

var_dump($t->getName());
var_dump($t->isBuiltin());
var_dump($t->allowsNull());
*/
