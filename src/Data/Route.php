<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\Validator;

class Route extends ValueObject
{
    const PUBLIC = 0;
    const HYBRID = 1;
    const PROTECTED_BY_COMMENT = 2;
    const PROTECTED_METHOD = 3;
    /**
     * @var string target uri
     */
    public $url;

    /**
     * @var callable
     */
    public $action;

    /**
     * @var array[Param]
     */
    public $parameters = [];

    /**
     * @var int access level
     */
    public $access = self::PUBLIC;

    public $requestMediaTypes = ['application/json'];
    public $responseMediaTypes = ['application/json'];

    /**
     * @var string
     */
    public $summary;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Param
     */
    public $return;

    /**
     * @var array
     */
    public $responses = [
        //200 => [
        //  'message'=> 'OK',
        //  'type'=> Class::name,
        //  'description'=> '',
        //],
        //404 => [
        //  'message'=> 'Not Found',
        //  'type'=> Exception:name,
        //  'description'=> '',
        //],
    ];

    /**
     * @var array
     */
    private $arguments = [];

    public static function parse(array $call): Route
    {
        $transform = [
            //----- RENAME -----------
            'url' => 'url',
            'className' => ['action', 0],
            'methodName' => ['action', 1],
            'accessLevel' => 'access',
            'summary' => 'description',
            'description' => 'longDescription',
            //----- REMOVE -----------
            'metadata' => true,
            'arguments' => true,
            'defaults' => true,
            'access' => true,

        ];
        $args = [];
        foreach ($call as $key => $value) {
            if ($k = $transform[$key] ?? false) {
                if (is_array($k)) {
                    $args[$k[0]][$k[1]] = $value;
                } elseif (true !== $k) {
                    $args[$k] = $value;
                }
            } else {
                $args[$key] = $value;
            }
        }
        $meta = $call['metadata'] ?? [];
        $return = $meta['return'] ?? ['type' => 'array'];
        unset($meta['return']);
        $args['return'] = Returns::parse($return);
        $params = $meta['param'];
        unset($meta['param']);
        $classes = $meta['class'] ?? [];
        unset($meta['class']);
        $scope = $meta['scope'] ?? [];
        unset($meta['scope']);
        foreach ($transform as $key => $value) {
            unset($meta[$key]);
        }
        $route = new static();
        $route->applyProperties($args);
        $route->applyProperties($meta);
        foreach ($classes as $class => $value) {
            $class = $scope[$class] ?? $class;
            $value = $value[CommentParser::$embeddedDataName] ?? [];
            foreach ($value as $k => $v) {
                $route->set[$class][$k] = $v;
            }
        }
        foreach ($params as $param) {
            $route->addParameter(Param::parse($param));
        }
        return $route;
    }

    public function addParameter(Param $parameter)
    {
        $parameter->index = count($this->parameters);
        $this->parameters[$parameter->name] = $parameter;
    }

    public function apply(array $arguments): array
    {
        $p = [];
        foreach ($this->parameters as $parameter) {
            $p[$parameter->index] = $arguments[$parameter->name]
                ?? $arguments[$parameter->index]
                ?? $parameter->default
                ?? null;
        }
        if (empty($p) && !empty($arguments)) {
            $this->arguments = array_values($arguments);
        } else {
            $this->arguments = $p;
        }
        return $p;
    }

    public function body()
    {
        return array_filter($this->parameters, function ($v) {
            return $v->from === 'body';
        });
    }

    public function call(array $arguments = null)
    {
        if (is_array($arguments)) {
            $this->apply($arguments);
        }
        foreach ($this->parameters as $parameter) {
            $i = $parameter->index;
            $this->arguments[$i] = Validator::validate($this->arguments[$i], $parameter);
        }
        //if (!is_callable($this->action)) {
        if (is_array($this->action) && count($this->action) && class_exists($this->action[0])) {
            $this->action[0] = new $this->action[0];
        }
        //}
        return call_user_func_array($this->action, $this->arguments);
    }

}