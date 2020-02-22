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

    /**
     * @param null|string $name {@type password}
     * @return string|null
     */
    function welcome(string $name): ?string
    {
        return "welcome $name!";
    }
}

echo ($type = Type::fromProperty(new ReflectionProperty(Test::class, 'obj'))) . PHP_EOL;
echo ($type = Type::__set_state($type->jsonSerialize())) . PHP_EOL;


$method = new ReflectionMethod(Test::class, 'welcome');
//var_dump(CommentParser::parse($method->getDocComment()));
print_r(Param::fromFunction($method));

/*

$p = new ReflectionProperty(Type::class, 'format');

$t = $p->getType();

var_dump($t->getName());
var_dump($t->isBuiltin());
var_dump($t->allowsNull());
*/
