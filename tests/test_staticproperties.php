<?php declare(strict_types=1);


use Luracast\Restler\Container;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\StaticProperties;

include __DIR__ . "/../vendor/autoload.php";

$a = StaticProperties::fromArray(['a' => true]);
$b = StaticProperties::fromArray(['b' => 5]);

var_export($a->merge($b));
die();

class Holder
{
    /**
     * @var StaticProperties
     */
    private $html;

    public function __construct(StaticProperties $html)
    {
        $this->html = $html;
    }

    function a()
    {
        $this->html['viewPath'] = 'good';
        $this->html->data->age = 26;
    }

    function b()
    {
        var_export($this->html);
    }
}

/*
$html = new StaticProperties(get_class_vars(Html::class));
$config = new StaticProperties(compact('html'));
$container = new Container($config);
$holder = new Holder($html);
*/

$container = new Container($config);
$holder = $container->make(Holder::class);


$holder->a();
$holder->b();

var_export($container->config('html'));

