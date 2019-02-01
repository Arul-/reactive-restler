<?php declare(strict_types=1);


use Luracast\Restler\Cache\HumanReadableCache;
use Luracast\Restler\Container;
use Luracast\Restler\Contracts\CacheInterface;
use Luracast\Restler\Defaults;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\StaticProperties;

include __DIR__ . "/../vendor/autoload.php";

$p = new StaticProperties(Defaults::class);

var_dump($p->supportedCharsets);

var_dump(isset($p->supportedCharsets));

$p->supportedCharsets[] = "iso9000";
var_dump($p->supportedCharsets);
var_dump(Defaults::$supportedCharsets);

array_shift($p->supportedCharsets);
var_dump($p->supportedCharsets);

Defaults::$supportedCharsets[]='EF121';
var_dump($p->supportedCharsets);

$p->implementations[CacheInterface::class][] = HumanReadableCache::class;

var_dump($p->implementations);

/*
$a = StaticProperties::fromArray(['a' => true]);
$b = StaticProperties::fromArray(['b' => 5]);

var_export($a->merge($b));
die();

class Holder
{
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
*/
/*
$html = new StaticProperties(get_class_vars(Html::class));
$config = new StaticProperties(compact('html'));
$container = new Container($config);
$holder = new Holder($html);
*/
/*
$container = new Container($config);
$holder = $container->make(Holder::class);


$holder->a();
$holder->b();

var_export($container->config('html'));
*/
