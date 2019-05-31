<?php namespace Luracast\Restler\OpenApi3;

use Luracast\Restler\Contracts\{
    ExplorableAuthenticationInterface,
    ProvidesMultiVersionApiInterface,
    UsesAuthenticationInterface
};
use Luracast\Restler\Core;
use Luracast\Restler\Data\{ApiMethodInfo, Param, Route, Type};
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Exceptions\Redirect;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\{ClassName, PassThrough, Text};
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

class Explorer implements ProvidesMultiVersionApiInterface
{
    const OPEN_API_SPEC_VERSION = '3.0.0';
    public static $infoClass = Info::class;
    public static $excludedPaths = ['_'];
    public static $excludedHttpMethods = ['OPTIONS'];
    public static $hideProtected = false;
    public static $allowScalarValueOnRequestBody = false;
    public static $servers = [];
    /**
     * @var array mapping PHP types to JS
     */
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
    protected static $prefixes = [
        'get' => 'retrieve',
        'index' => 'list',
        'post' => 'create',
        'put' => 'update',
        'patch' => 'modify',
        'delete' => 'remove',
    ];
    protected $models = [];
    protected $requestBodies = [];
    /**
     * @var ServerRequestInterface
     */
    private $request;
    /**
     * @var Core
     */
    private $restler;
    /**
     * @var Route
     */
    private $route;

    public function __construct(ServerRequestInterface $request, Route $route, Core $restler)
    {
        $this->request = $request;
        $this->restler = $restler;
        $this->route = $route;
    }

    public static function getMaximumSupportedVersion(): int
    {
        return Router::$maximumVersion;
    }

    /**
     * Serve static files for explorer
     * @throws HttpException
     */
    public function index()
    {
        $base = $this->route->url;
        $path = $this->request->getUri()->getPath();
        if (!Text::contains($path, $base)) {
            //if not add and redirect
            throw new Redirect((string)$this->request->getUri() . '/index.html');
        }
        return $this->get('index.html');
    }

    /**
     * @param $filename
     * @return \Psr\Http\Message\ResponseInterface
     * @throws HttpException
     *
     * @url GET /{filename}
     */
    public function get($filename)
    {
        $filename = str_replace(array('../', './', '\\', '..', '.php'), '', $filename);
        if (empty($filename)) {
            $filename = 'index.html';
        } elseif ('oauth2-redirect' == $filename) {
            $filename .= '.html';
        }
        $file = __DIR__ . '/client/' . $filename;
        return PassThrough::file($file, $this->request->getHeaderLine('If-Modified-Since'));
    }

    /**
     * @return stdClass
     * @throws HttpException
     */
    public function docs()
    {
        $s = new stdClass();
        $s->openapi = static::OPEN_API_SPEC_VERSION;

        $r = $this->restler;
        $version = (string)$r->requestedApiVersion;
        $s->info = $this->info($version);
        $s->servers = $this->servers();
        $s->paths = $this->paths($version);
        $s->components = $this->components();
        return $s;
    }

    private function info(int $version)
    {
        $info = array_filter(call_user_func(static::$infoClass . '::format', static::OPEN_API_SPEC_VERSION));
        $info['version'] = (string)$version;
        return $info;
    }

    /**
     * @return array
     */
    private function servers()
    {
        return empty(static::$servers)
            ? [['url' => (string)$this->restler->baseUrl, 'description' => 'server']]
            : static::$servers;
    }

    /**
     * @param int $version
     * @return array
     * @throws HttpException
     */
    private function paths(int $version = 1)
    {
        $selfExclude = empty($this->route->path) ? ['', '{s0}', 'docs'] : [$this->route->path];
        $map = Router::findAll(
            $this->request, [$this->restler, 'make'], static::$excludedPaths + $selfExclude,
            static::$excludedHttpMethods, $version
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
        foreach ($route->authClasses as $authClass) {
            $r->security[][ClassName::short($authClass)] = [];
        }
        $r->summary = $route->summary ?? '';
        $r->description = $route->description ?? '';
        $r->responses = $this->responses($route);
        if (property_exists($route, 'deprecated')) {
            $r->deprecated = true;
        }
        return $r;
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

    private function parameters(Route $route)
    {
        $parameters = $route->filterParams(false);
        $body = $route->filterParams(true);
        $bodyValues = array_values($body);
        $r = array();
        $requestBody = null;
        foreach ($parameters as $param) {
            $r[] = $this->parameter($param);
        }
        if (!empty($body)) {
            if (
                1 == count($bodyValues) &&
                (static::$allowScalarValueOnRequestBody || !empty($bodyValues[0]->children))
            ) {
                $requestBody = $this->requestBody($route, $bodyValues[0]);
            } else {
                //lets group all body parameters under a generated model name
                $name = $this->modelName($route);
                $children = [];
                /**
                 * @var string $name
                 * @var Param $child
                 */
                foreach ($body as $cname => $child) {
                    $children[$cname] = $child->jsonSerialize();
                }
                $requestBody = $this->requestBody($route, Param::__set_state([
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

    private function setType(&$object, Type $param)
    {
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
        $this->models[$type] = $r;

        return $r;
    }

    private function requestBody(Route $route, Param $param, $description = '')
    {
        $p = $this->parameter($param, $description);
        $content = [];
        foreach ($route->requestMediaTypes as $mime) {
            $content[$mime] = ['schema' => $p->schema];
            if (Xml::MIME === $mime) {
                $content[$mime] += ['xml' => ['name' => Xml::$defaultTagName]];
            }
        }
        $this->requestBodies[$param->type] = compact('content');
        return (object)['$ref' => "#/components/requestBodies/{$param->type}"];
    }

    private function modelName(Route $route)
    {
        return ucfirst($this->operationId($route)) . 'Model';
    }

    private function responses(Route $route)
    {
        $code = '200';
        if (isset($route->status)) {
            $code = $route->status;
        }
        $schema = new stdClass();
        $content = [];
        foreach ($route->responseMediaTypes as $mime) {
            $content[$mime] = ['schema' => $schema];
            if (Xml::MIME === $mime) {
                $content[$mime] += ['xml' => ['name' => Xml::$defaultTagName]];
            }
        }
        $r = array(
            $code => array(
                'description' => HttpException::$codes[$code] ?? 'Success',
                'content' => $content
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

    private function components()
    {
        $c = (object)[
            'schemas' => new stdClass(),
            'requestBodies' => (object)$this->requestBodies,
            'securitySchemes' => $this->securitySchemes(),
        ];
        foreach ($this->models as $type => $model) {
            $c->schemas->{$type} = $model;
        }
        return $c;
    }

    private function securitySchemes()
    {
        $schemes = [];
        foreach (Router::$authClasses as $class) {
            if (class_implements($class)[ExplorableAuthenticationInterface::class] ?? false) {
                $schemes[ClassName::short($class)] = (object)$class::scheme()->toArray();
            }
        }
        return (object)$schemes;
    }
}