<?php


namespace Luracast\Restler\Data;


use Luracast\Restler\Contracts\{RequestMediaTypeInterface, ResponseMediaTypeInterface, ValidationInterface};
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\{ClassName, CommentParser, Validator};
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Route extends ValueObject
{
    const ACCESS_PUBLIC = 0;
    const ACCESS_HYBRID = 1;
    const ACCESS_PROTECTED_BY_COMMENT = 2;
    const ACCESS_PROTECTED_METHOD = 3;


    const PROPERTY_TAGS = [
        'summary',
        'description',
        'status',
        'header',
        'cache',
        'expires',
        'throttle',
        'throttle',
        'view',
        'error-view' => 'errorView',
    ];

    const INTERNAL_TAGS = [
        'param',
        'return',
    ];

    const METHOD_TAGS = [
        'access' => 'setAccess',
        'class' => 'setClassProperties',
        'throws' => 'computeResponses',
        'format' => 'overrideFormats',
        'request-format' => 'overrideFormats',
        'response-format' => 'overrideFormats',
    ];

    public $httpMethod = 'GET';
    /**
     * @var string target uri. human readable, for documentation
     */
    public $url;

    /**
     * @var string path used for routing
     */
    public $path;

    /**
     * @var string
     */
    public $summary = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var callable
     */
    public $action;

    /**
     * @var Param[]
     */
    public $parameters = [];

    /**
     * @var Returns
     */
    public $return;

    /**
     * @var int http status
     */
    public $status = 200;

    /**
     * @var array headers set through comments
     */
    public $header = [];

    /**
     * @var string[] cache setting from comments
     */
    public $cache = [];

    /**
     * @var int|null
     */
    public $expires;

    /**
     * @var int|null
     */
    public $throttle;

    /**
     * @var array
     */
    public $responses = [
        /*
        200 => [
          'message'=> 'OK',
          'type'=> Class::name,
          'description'=> '',
        ],
        404 => [
          'message'=> 'Not Found',
          'type'=> Exception:name,
          'description'=> '',
        ],
        */
    ];

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

    public $authClasses = [];
    public $preAuthFilterClasses = [];
    public $postAuthFilterClasses = [];
    /**
     * @var array [class => [property => $value ...]...]
     * values to set on initialization of classes
     */
    public $set = [];

    /**
     * @var array
     */
    protected $arguments = [];

    private function setAccess(string $name, ?string $access = null, ReflectionFunctionAbstract $function, ?array $metadata = null, array $scope = []): void
    {
        if ($function->isProtected()) {
            $this->access = self::ACCESS_PROTECTED_METHOD;
        } elseif (is_string($access)) {
            if ('protected' == $access) {
                $this->access = self::ACCESS_PROTECTED_BY_COMMENT;
            } elseif ('hybrid' == $access) {
                $this->access = self::ACCESS_HYBRID;
            }
        } elseif (isset($metadata['protected'])) {
            $this->access = self::ACCESS_PROTECTED_BY_COMMENT;
        }
    }

    private function setClassProperties(string $name, ?array $class = null, ReflectionFunctionAbstract $function, ?array $metadata = null, array $scope = []): void
    {
        $classes = $class ?? [];
        foreach ($classes as $class => $value) {
            $class = ClassName::resolve($class, $scope);
            $value = $value[CommentParser::$embeddedDataName] ?? [];
            foreach ($value as $k => $v) {
                $this->set[$class][$k] = $v;
            }
        }
    }

    private function computeResponses(string $name, ?array $throws = null, ReflectionFunctionAbstract $function, ?array $metadata = null, array $scope = []): void
    {
        $classes = $class ?? [];
        foreach ($classes as $class => $value) {
            $class = ClassName::resolve($class, $scope);
            $value = $value[CommentParser::$embeddedDataName] ?? [];
            foreach ($value as $k => $v) {
                $this->set[$class][$k] = $v;
            }
        }
    }

    private function overrideFormats(string $name, ?array $formats = null, ReflectionFunctionAbstract $function, ?array $metadata = null, array $scope = []): void
    {
        if (!$formats) return;
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
        $overrides = [
            'Request' => Router::$requestMediaTypeOverrides,
            'Response' => Router::$responseMediaTypeOverrides,
        ];
        switch ($name) {
            case 'request-format':
                unset($overrides['Response']);
                $formats = array_map($resolver, $formats);
                $this->setRequestMediaTypes(...$formats);
                break;
            case 'response-format':
                unset($overrides['Request']);
                $formats = array_map($resolver, $formats);
                $this->setResponseMediaTypes(...$formats);
                break;
            default:
                $formats = array_map($resolver, $formats);
                $this->setRequestMediaTypes(...$formats);
                $this->setResponseMediaTypes(...$formats);

        }
    }


    public static function fromMethod(ReflectionMethod $method, ?array $metadata = null, array $scope = []): self
    {
        if (empty($scope)) {
            $scope = Router::scope($method->getDeclaringClass());
        }
        if (is_null($metadata)) {
            $metadata = CommentParser::parse($method->getDocComment());
        }
        $route = new self();
        foreach (self::PROPERTY_TAGS as $key => $property) {
            if (is_numeric($key)) {
                $key = $property;
            }
            if (isset($metadata[$key])) {
                $route->{$property} = $metadata[$key];
            }
        }

        $route->action = [$method->class, $method->getName()];
        $route->return = Returns::fromReturnType(
            $method->hasReturnType() ? $method->getReturnType() : null,
            $metadata['return'] ?? ['type' => ['array']],
            $scope
        );
        $route->parameters = Param::fromMethod($method, $metadata, $scope);
        foreach (self::METHOD_TAGS as $key => $func) {
            call_user_func([$route, $func], $key, $metadata[$key] ?? null, $method, $metadata, $scope);
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

        return $route;
    }

    public function withLink(string $url, string $httpMethod = 'GET'): self
    {
        $instance = clone $this;
        $instance->url = $url;
        $instance->httpMethod = $httpMethod;
        $prevPathParams = $instance->filterParams(true, Param::FROM_PATH);
        $pathParams = [];
        //compute from the human readable url to machine computable typed route path
        $instance->path = preg_replace_callback(
            '/{[^}]+}|:[^\/]+/',
            function ($matches) use (&$pathParams, $instance) {
                $match = trim($matches[0], '{}:');
                $param = $instance->parameters[$match];
                $param->from = Param::FROM_BODY;
                $param->required = true;
                $pathParams[$match] = $param;
                return '{' . Router::typeChar($param->type) . $param->index . '}';
            },
            $instance->url
        );
        $noBody = 'GET' === $httpMethod || 'DELETE' === $httpMethod;
        foreach ($prevPathParams as $name => $param) {
            //remap unused path parameters to query or body
            if (!isset($pathParams[$name])) {
                $param->from = $noBody ? Param::FROM_QUERY : Param::FROM_BODY;
            }
        }
        if ($noBody) {
            //map body parameters to query
            $bodyParams = $instance->filterParams(true, Param::FROM_BODY);
            foreach ($bodyParams as $name => $param) {
                $param->from = Param::FROM_QUERY;
            }
        }
        return $instance;
    }

    public static function make(callable $action, string $url, $httpMethod = 'GET', array $data = [])
    {
        return static::parse(compact('action', 'url', 'httpMethod') + $data);
    }

    public static function parse(array $call): Route
    {
        $transform = [
            //----- RENAME -----------
            'url' => 'url',
            'className' => ['action', 0],
            'methodName' => ['action', 1],
            'accessLevel' => 'access',
            'description' => 'summary',
            'longDescription' => 'description',
            //----- REMOVE -----------
            'metadata' => true,
            'arguments' => true,
            'defaults' => true,
            'access' => true,

        ];
        $extract = function (array &$from, string $key, $default = null) {
            $value = $from[$key] ?? $default;
            unset($from[$key]);
            return $value;
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
        $route = new static();
        $route->applyProperties($args);
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
        foreach ($transform as $key => $value) {
            unset($meta[$key]);
        }
        $route->applyProperties($meta);
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
        //compute from the human readable url to machine computable typed route path
        $route->path = preg_replace_callback(
            '/{[^}]+}|:[^\/]+/',
            function ($matches) use ($route) {
                $match = trim($matches[0], '{}:');
                $param = $route->parameters[$match];
                return '{' . Router::typeChar($param->type) . $param->index . '}';
            },
            $route->url
        );

        return $route;
    }

    public function setRequestMediaTypes(string ...$types): void
    {
        Router::_setMediaTypes(
            RequestMediaTypeInterface::class,
            $types,
            $this->requestFormatMap,
            $this->requestMediaTypes
        );
    }

    public function setResponseMediaTypes(string ...$types): void
    {
        Router::_setMediaTypes(
            ResponseMediaTypeInterface::class,
            $types,
            $this->responseFormatMap,
            $this->responseMediaTypes
        );
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

    public function __clone()
    {
        $this->parameters = array_map(function ($param) {
            return clone $param;
        }, $this->parameters);
        $this->return = clone $this->return;
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

    public function filterParams(bool $include, string $from = Param::FROM_BODY): array
    {
        return array_filter(
            $this->parameters,
            function ($v) use ($from, $include) {
                return $include ? $from === $v->from : $from !== $v->from;
            }
        );
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
