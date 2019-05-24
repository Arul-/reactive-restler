<?php namespace Luracast\Restler\OpenApi3;

use Luracast\Restler\Contracts\ProvidesMultiVersionApiInterface;
use Luracast\Restler\Contracts\UsesAuthenticationInterface;
use Luracast\Restler\Core;
use Luracast\Restler\Data\Route;
use Luracast\Restler\Data\Type;
use Luracast\Restler\Exceptions\Redirect;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Utils\Text;
use Luracast\Restler\Data\Param;
use Luracast\Restler\OpenApi3\Info;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\PassThrough;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

class Explorer implements ProvidesMultiVersionApiInterface, UsesAuthenticationInterface
{
    const SWAGGER = '3.0.0';
    public static $infoClass = Info::class;
    public static $excludedPaths = ['explorer', '_'];
    public static $excludedHttpMethods = ['OPTIONS'];
    public static $hideProtected = true;
    public static $allowScalarValueOnRequestBody = false;

    protected static $prefixes = [
        'get' => 'retrieve',
        'index' => 'list',
        'post' => 'create',
        'put' => 'update',
        'patch' => 'modify',
        'delete' => 'remove',
    ];
    protected $authenticated = false;

    protected $models = [];
    protected $requestBodies = [];

    public static $dataTypeAlias = [
        //'string' => 'string',
        'int' => 'integer',
        'number' => 'number',
        'float' => ['number', 'float'],
        'bool' => 'boolean',
        //'boolean' => 'boolean',
        //'NULL' => 'null',
        'array' => 'array',
        //'object'  => 'object',
        'stdClass' => 'object',
        'mixed' => 'string',
        'date' => ['string', 'date'],
        'datetime' => ['string', 'date-time'],

        'time' => 'string',
        'timestamp' => 'string',
    ];
    /**
     * @var ServerRequestInterface
     */
    private $request;
    /**
     * @var PassThrough
     */
    private $passThrough;
    /**
     * @var Core
     */
    private $restler;
    /**
     * @var \Luracast\Restler\Data\ApiMethodInfo
     */
    private $info;

    public function __construct(ServerRequestInterface $request, ApiMethodInfo $info, Core $restler)
    {
        $this->request = $request;
        $this->restler = $restler;
        $this->info = $info;
    }

    /**
     * Serve static files for explorer
     * @url GET *
     * @throws HttpException
     */
    public function index()
    {
        $base = rtrim($this->info->url, '*');
        $path = $this->request->getUri()->getPath();
        if (!Text::contains($path, $base)) {
            //if not add and redirect
            throw new Redirect((string)$this->request->getUri() . '/');
        }
        $args = func_get_args();
        $filename = implode('/', $args);
        $filename = str_replace(array('../', './', '\\', '..', '.php'), '', $filename);
        if (empty($filename)) {
            $filename = 'index.html';
        }
        $file = __DIR__ . '/client/' . $filename;
        return PassThrough::file($file, $this->request->getHeaderLine('If-Modified-Since'));
    }

    /**
     * @return stdClass
     * @throws HttpException
     */
    public function swagger()
    {
        $s = new stdClass();
        $s->openapi = static::SWAGGER;

        $r = $this->restler;
        $version = (string)$r->requestedApiVersion;
        $s->info = $this->info($version);
        $s->servers = $this->servers();

        $s->paths = $this->paths($s->servers[0]['url'], $version);

        $s->components = $this->components();
        return $s;
    }

    private function info(int $version)
    {
        $info = array_filter(call_user_func(static::$infoClass . '::format', static::SWAGGER));
        $info['version'] = (string)$version;
        return $info;
    }

    /**
     * @return array
     */
    private function servers()
    {
        return [['url' => (string)$this->restler->baseUrl, 'description' => 'server']];
    }

    /**
     * @param string $basePath
     * @param int $version
     * @return array
     * @throws HttpException
     */
    private function paths(string $basePath, int $version = 1)
    {
        $map = Router::findAll(
            static::$excludedPaths + [$basePath],
            static::$excludedHttpMethods, $version, $this->authenticated
        );
        $paths = array();
        foreach ($map as $path => $data) {
            $access = $data[0]['access'];
            if (static::$hideProtected && !$access) {
                continue;
            }
            foreach ($data as $item) {
                $route = $item['route'];
                $access = $item['access'];
                if (static::$hideProtected && !$access) {
                    continue;
                }
                $url = $route->url;
                $paths["/$url"][strtolower($route->httpMethod)] = $this->operation($route);
            }
        }
        return $paths;
    }

    private function operation(Route $route)
    {
        $r = new stdClass();
        $r->operationId = $this->operationId($route);
        $base = strtok($route->url, '/');
        if (empty($base)) {
            $base = 'root';
        }
        $r->tags = array($base);
        [$r->parameters, $r->requestBody] = $this->parameters($route);

        if (is_null($r->requestBody)) {
            unset($r->requestBody);
        }

        $r->summary = $route->summary ?? '';
        $r->description = $route->description ?? '';
        $r->responses = $this->responses($route);
        //TODO: avoid hard coding. Properly detect security
        if ($route->access) {
            $r->security = array(array('api_key' => array()));
        }
        /*
        $this->setType(
            $r,
            new Param($m['return'] ?? [])
        );
        if (is_null($r->type) || 'mixed' == $r->type) {
            $r->type = 'array';
        } elseif ($r->type == 'null') {
            $r->type = 'void';
        } elseif (Text::contains($r->type, '|')) {
            $r->type = 'array';
        }
        */
        //TODO: add $r->authorizations
        //A list of authorizations required to execute this operation. While not mandatory, if used, it overrides
        //the value given at the API Declaration's authorizations. In order to completely remove API Declaration's
        //authorizations completely, an empty object ({}) may be applied.
        //TODO: add $r->produces
        //TODO: add $r->consumes
        //A list of MIME types this operation can produce/consume. This is overrides the global produces definition at the root of the API Declaration. Each string value SHOULD represent a MIME type.
        //TODO: add $r->deprecated
        //Declares this operation to be deprecated. Usage of the declared operation should be refrained. Valid value MUST be either "true" or "false". Note: This field will change to type boolean in the future.
        return $r;
    }

    private function components()
    {
        $c = (object)[
            'schemas' => new stdClass(),
            'requestBodies' => $this->requestBodies,
            'securitySchemes' => $this->securitySchemes(),
        ];
        foreach ($this->models as $type => $model) {
            $c->schemas->{$type} = $model;
        }
        return $c;
    }

    private function parameters(Route $route)
    {
        $parameters = $route->filterParams(false);
        $body = array_values($route->filterParams(true));
        $r = array();
        $requestBody = null;
        foreach ($parameters as $param) {
            $r[] = $this->parameter($param);
        }
        if (!empty($body)) {
            if (
                1 == count($body) &&
                (static::$allowScalarValueOnRequestBody || !empty($body[0]->children))
            ) {
                $requestBody = $this->requestBody($body[0]);
            } else {
                //lets group all body parameters under a generated model name
                $name = $this->modelName($route);
                $children = [];
                /**
                 * @var string $name
                 * @var Param $child
                 */
                foreach ($body as $name => $child) {
                    $children[$name] = $child->jsonSerialize();
                }
                $requestBody = $this->requestBody(Param::__set_state([
                    'name' => $name,
                    'type' => $name,
                    'from' => 'body',
                    'required' => true,
                    'children' => $children
                ]));
            }
        }
        return [$r, $requestBody];
    }

    private function requestBody(Param $param, $description = '')
    {
        $p = $this->parameter($param, $description);
        $this->requestBodies[$param->type] = [
            'content' => ['application/json' => ['schema' => $p->schema]]
        ];
        return (object)['$ref' => "#/components/requestBodies/{$param->type}"];
    }

    private function parameter(Param $param, $description = '')
    {
        $p = (object)[
            'name' => '',
            'in' => 'query',
            'description' => '',
            'required' => false,
            'schema' => new stdClass()
        ];
        //if (isset($info->rules['model'])) {
        //$info->type = $info->rules['model'];
        //}
        $p->name = $param->name;
        $this->setType($p->schema, $param);
        if (empty($param->children) || $param->type != 'array') {
            //primitives
            if ($param->default) {
                $p->defaultValue = $param->default;
            }
            if ($param->choice) {
                $p->enum = $param->choice;
            }
            if ($param->min) {
                $p->minimum = $param->min;
            }
            if ($param->max) {
                $p->maximum = $param->max;
            }
            //TODO: $p->items and $p->uniqueItems boolean
        }
        $p->description = $description;
        $p->in = $param->from; //$info->from == 'body' ? 'form' : $info->from;
        $p->required = $param->required;

        //$p->allowMultiple = false;

        if (isset($p->{'$ref'})) {
            $p->schema = (object)array('$ref' => ($p->{'$ref'}));
            unset($p->{'$ref'});
        }

        return $p;
    }

    private function responses(Route $route)
    {
        $code = '200';
        if (isset($route->status)) {
            $code = $route->status;
        }
        $schema = new stdClass();
        $r = array(
            $code => array(
                'description' => HttpException::$codes[$code] ?? 'Success',
                'content' => ["application/json" => ['schema' => $schema]]
            )
        );
        $return = $route->return;
        if (!empty($return)) {
            $this->setType($schema, $return);
        }

        if (is_array($throws = $route->throws ?? null)) {
            foreach ($throws as $message) {
                $r[$message['code']] = array('description' => $message['message']);
            }
        }

        return $r;
    }

    private function model($type, array $children)
    {
        if (isset($this->models[$type])) {
            return $this->models[$type];
        }
        $r = new stdClass();
        $r->type = 'object';
        $r->properties = array();
        $required = array();
        foreach ($children as $child) {
            $info = Param::parse($child);
            $p = new stdClass();
            $this->setType($p, $info);
            if (isset($child['description'])) {
                $p->description = $child['description'];
            }
            if ($info->default) {
                $p->defaultValue = $info->default;
            }
            if ($info->choice) {
                $p->enum = $info->choice;
            }
            if ($info->min) {
                $p->minimum = $info->min;
            }
            if ($info->max) {
                $p->maximum = $info->max;
            }
            if ($info->required) {
                $required[] = $info->name;
            }
            $r->properties[$info->name] = $p;
        }
        if (!empty($required)) {
            $r->required = $required;
        }
        $r->xml = ['name' => $type];
        //TODO: add $r->subTypes https://github.com/wordnik/swagger-spec/blob/master/versions/1.2.md#527-model-object
        //TODO: add $r->discriminator https://github.com/wordnik/swagger-spec/blob/master/versions/1.2.md#527-model-object
        $this->models[$type] = $r;

        return $r;
    }

    private function setType(&$object, Type $param)
    {
        //TODO: proper type management
        $type = ClassName::short($param->type);
        if ($param->type == 'array') {
            $object->type = 'array';
            if ($param->children) {
                $contentType = ClassName::short($param->contentType);
                $model = $this->model($contentType, $param->children);
                $object->items = (object)array(
                    '$ref' => "#/components/schemas/$contentType"
                );
            } elseif ($param->contentType && $param->contentType == 'associative') {
                unset($param->contentType);
                $this->model($param->type = 'Object', array(
                    array(
                        'name' => 'property',
                        'type' => 'string',
                        'default' => '',
                        'required' => false,
                        'description' => ''
                    )
                ));
            } elseif ($param->contentType && $param->contentType != 'indexed') {
                if (is_string($param->contentType) &&
                    $t = static::$dataTypeAlias[strtolower($param->contentType)] ?? null) {
                    if (is_array($t)) {
                        $object->items = (object)array(
                            'type' => $t[0],
                            'format' => $t[1],
                        );
                    } else {
                        $object->items = (object)array(
                            'type' => $t,
                        );
                    }
                } else {
                    $contentType = ClassName::short($param->contentType);
                    $object->items = (object)array(
                        '$ref' => "#/components/schemas/$contentType"
                    );
                }
            } else {
                $object->items = (object)array(
                    'type' => 'string'
                );
            }
        } elseif ($param->children) {
            $this->model($type, $param->children);
            $object->{'$ref'} = "#/components/schemas/$type";
        } elseif (is_string($param->type) && $t = static::$dataTypeAlias[strtolower($param->type)] ?? null) {
            if (is_array($t)) {
                $object->type = $t[0];
                $object->format = $t[1];
            } else {
                $object->type = $t;
            }
        } else {
            $object->type = 'string';
        }
        $has64bit = PHP_INT_MAX > 2147483647;
        if (isset($object->type)) {
            if ($object->type == 'integer') {
                $object->format = $has64bit
                    ? 'int64'
                    : 'int32';
            } elseif ($object->type == 'number') {
                $object->format = $has64bit
                    ? 'double'
                    : 'float';
            }
        }
    }

    private function operationId(Route $route)
    {
        static $hash = array();
        $id = $route->httpMethod . ' ' . $route->url;
        if (isset($hash[$id])) {
            return $hash[$id];
        }
        if (is_array($route->action) && 2 == count($route->action) && is_string($route->action[0])) {
            $class = ClassName::short($route->action[0]);
            $method = $route->action[1];
            if (isset(static::$prefixes[$method])) {
                $method = static::$prefixes[$method] . $class;
            } else {
                $method = str_replace(
                    array_keys(static::$prefixes),
                    array_values(static::$prefixes),
                    $method
                );
                $method = lcfirst($class) . ucfirst($method);
            }
            $hash[$id] = $method;
            return $method;
        }
        $hash[$id] = $id;
        return $id;
    }

    private function modelName(Route $route)
    {
        return ucfirst($this->operationId($route)) . 'Model';
    }

    private function securitySchemes()
    {
        return (object)[
            'apiKey' => (object)[
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'key',
            ]
        ];
    }

    public static function getMaximumSupportedVersion(): int
    {
        return Router::$maximumVersion;
    }

    public function _setAuthenticationStatus(bool $isAuthenticated = false, bool $isAuthFinished = false)
    {
        $this->authenticated = $isAuthenticated;
    }
}