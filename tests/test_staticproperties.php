<?php declare(strict_types=1);


use Luracast\Restler\Container;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\StaticProperties;

include __DIR__ . "/../vendor/autoload.php";

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
    }

    function b()
    {
        var_export($this->html);
    }
}

$html = new StaticProperties(get_class_vars(Html::class));

#$container = new Container();
#$html = $container->make(Holder::class);

$holder = new Holder($html);

$holder->a();
$holder->b();