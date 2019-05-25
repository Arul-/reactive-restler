<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Contracts\{RequestMediaTypeInterface, ResponseMediaTypeInterface, ValidationInterface};
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\{ClassName, CommentParser, Validator};
use ReflectionMethod;

class Route extends ValueObject
{
    const ACCESS_PUBLIC = 0;
    const ACCESS_HYBRID = 1;
    const ACCESS_PROTECTED_BY_COMMENT = 2;
    const ACCESS_PROTECTED_METHOD = 3;
    /**
     * @var string target uri
     */
    public $url;

    public $httpMethod = 'GET';

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
    public $access = self::ACCESS_PUBLIC;

    public $requestMediaTypes = [];
    public $responseMediaTypes = [];

    /**
     * @var array
     * @internal
     */
    public $requestFormatMap = [];
    /**
     * @var array
     * @internal
     */
    public $responseFormatMap = [];

    /**
     * @var string
     */
    public $summary;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Returns
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
        $extract = function (array &$from, string $key, $default = null) {
            if ($value = $from[$key] ?? false) {
                unset($from[$key]);
                return $value;
            }
            return $default;
        };
        $meta = $extract($call, 'metadata', []);
        $args = [
            'summary' => $extract($meta, 'description', ''),
            'description' => $extract($meta, 'longDescription', ''),
            'return' => Returns::parse($extract($meta, 'return', ['type' => 'array'])),
        ];
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
        $params = $extract($meta, 'param', []);
        $classes = $extract($meta, 'class', []);
        $scope = $extract($meta, 'scope', []);
        foreach ($transform as $key => $value) {
            unset($meta[$key]);
        }
        $route = new static();
        $route->applyProperties($args);
        $route->applyProperties($meta);
        $overrides = [];
        $resolver = function ($value) use ($scope, &$overrides) {
            $value = ClassName::resolve(trim($value), $scope);
            foreach ($overrides as $key => $override) {
                if (false === array_search($value, $override)) {
                    throw new HttpException(
                        500,
                        "Given media type is not present in overriding list. " .
                        "Please call `Router::setOverriding{$key}MediaTypes(\"$value\");` before other router methods."
                    );
                }
            }
            return $value;
        };
        if ($formats = $meta['format'] ?? false) {
            unset($meta['format']);
            $overrides = [
                'Request' => Router::$requestMediaTypeOverrides,
                'Response' => Router::$responseMediaTypeOverrides,
            ];
            $formats = explode(',', $formats);
            $formats = array_map($resolver, $formats);
            $route->setRequestMediaTypes(...$formats);
            $route->setResponseMediaTypes(...$formats);
        }
        if ($formats = $meta['response-format'] ?? false) {
            unset($meta['response-format']);
            $overrides = [
                'Response' => Router::$responseMediaTypeOverrides,
            ];
            $formats = explode(',', $formats);
            $formats = array_map($resolver, $formats);
            $route->setResponseMediaTypes(...$formats);
        }
        if ($formats = $meta['request-format'] ?? false) {
            unset($meta['request-format']);
            $overrides = [
                'Request' => Router::$requestMediaTypeOverrides,
            ];
            $formats = explode(',', $formats);
            $formats = array_map($resolver, $formats);
            $route->setRequestMediaTypes(...$formats);
        }
        if (empty($route->responseMediaTypes)) {
            $route->responseMediaTypes = Router::$responseMediaTypes;
        }
        if (empty($route->requestFormatMap)) {
            $route->requestFormatMap = Router::$requestFormatMap;
        } elseif (empty($route->requestFormatMap['default'])) {
            $route->requestFormatMap['default'] = array_values($route->requestFormatMap)[0];
        }
        if (empty($route->requestMediaTypes)) {
            $route->requestMediaTypes = Router::$requestMediaTypes;
        }
        if (empty($route->responseFormatMap)) {
            $route->responseFormatMap = Router::$responseFormatMap;
        } elseif (empty($route->responseFormatMap['default'])) {
            $route->responseFormatMap['default'] = array_values($route->responseFormatMap)[0];
        }
        foreach ($classes as $class => $value) {
            $class = ClassName::resolve($class, $scope);
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

    public function setRequestMediaTypes(string ...$types): void
    {
        Router::_setMediaTypes(RequestMediaTypeInterface::class, $types,
            $this->requestFormatMap, $this->requestMediaTypes);
    }

    public function setResponseMediaTypes(string ...$types): void
    {
        Router::_setMediaTypes(ResponseMediaTypeInterface::class, $types,
            $this->responseFormatMap, $this->responseMediaTypes);
    }

    public function addParameter(Param $parameter)
    {
        $parameter->index = count($this->parameters);
        $this->parameters[$parameter->name] = $parameter;
    }

    public function call(array $arguments, bool $validate = true, callable $maker = null)
    {
        if (!$maker) {
            $maker = function ($class) {
                return new $class;
            };
        }
        $this->apply($arguments);
        if ($validate) {
            $this->validate($maker(Validator::class), $maker);
        }
        return $this->handle(1, $maker);

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

    public function validate(ValidationInterface $validator, callable $maker)
    {
        foreach ($this->parameters as $param) {
            $i = $param->index;
            $info = &$param->rules;
            if (!isset ($info['validate']) || $info['validate'] != false) {
                if (isset($info['method'])) {
                    $param->apiClassInstance = $maker($this->action[0]);
                }
                $value = $this->arguments[$i];
                $this->arguments[$i] = null;
                if (empty(Validator::$exceptions)) {
                    $info['autofocus'] = true;
                }
                $this->arguments[$i] = $validator::validate($value, $param);
                unset($info['autofocus']);
            }
        }
    }

    public function handle(int $access, callable $maker)
    {
        $action = $this->action;
        switch ($access) {
            case self::ACCESS_PROTECTED_METHOD:
                $object = $maker($action[0]);
                $reflectionMethod = new ReflectionMethod(
                    $object,
                    $action[1]
                );
                $reflectionMethod->setAccessible(true);
                return $reflectionMethod->invokeArgs(
                    $object,
                    $this->arguments
                );
            default:
                if (is_array($action) && count($action) && is_string($action[0]) && class_exists($action[0])) {
                    $action[0] = $maker($action[0]);
                }
                return call_user_func_array($action, $this->arguments);
        }
    }

    public function filterParams(bool $body): array
    {
        return array_filter($this->parameters, function ($v) use ($body) {
            return $body ? $v->from === 'body' : $v->from !== 'body';
        });
    }
}