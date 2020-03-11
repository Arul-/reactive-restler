<?php declare(strict_types=1);

use Luracast\Restler\Data\Param;
use Luracast\Restler\Data\Type;
use Luracast\Restler\Utils\CommentParser;

include __DIR__ . "/../vendor/autoload.php";

$type = Type::__set_state(['type' => 'int', 'multiple' => true, 'scalar' => true]);

echo $type . PHP_EOL;

class Test
{
    /** @var null|array {@type integer|null} an array of integers */
    public array $obj;
    /** @var null|array {@type float} an array of integers */
    public $arr;

    /**
     * @param null|string $name {@type password}
     * @return string|null
     */
    function welcome(string $name): ?string
    {
        return "welcome $name!";
    }
}

echo ($type = Type::fromProperty($obj = new ReflectionProperty(Test::class, 'obj'))) . PHP_EOL;
echo ($type = Type::__set_state($type->jsonSerialize())) . PHP_EOL;
$arr = new ReflectionProperty(Test::class, 'arr');
echo ($type = Type::fromProperty(null, CommentParser::parse($arr->getDocComment())['var'])) . PHP_EOL;


$method = new ReflectionMethod(Test::class, 'welcome');
//var_dump(CommentParser::parse($method->getDocComment()));
print_r(Param::fromMethod($method));

echo ($type = Type::fromClass(new ReflectionClass(Test::class))) . PHP_EOL;
print_r($type);

/*

$p = new ReflectionProperty(Type::class, 'format');

$t = $p->getType();

var_dump($t->getName());
var_dump($t->isBuiltin());
var_dump($t->allowsNull());
*/
