<?php

namespace Luracast\Restler\OpenApi3;

use Luracast\Restler\Contracts\{AuthenticationInterface,
    ComposerInterface,
    DownloadableFileMediaTypeInterface,
    ExplorableAuthenticationInterface,
    ProvidesMultiVersionApiInterface
};
use Luracast\Restler\Core;
use Luracast\Restler\Data\{Param, Returns, Route, Type};
use Luracast\Restler\Defaults;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Exceptions\Redirect;
use Luracast\Restler\OpenApi3\Tags\TagByBasePath;
use Luracast\Restler\OpenApi3\Tags\Tagger;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\{ClassName, PassThrough, Text, Type as TypeUtil};
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionClass;
use ReflectionException;
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
     * @var array
     * @link https://swagger.io/docs/open-source-tools/swagger-ui/usage/configuration
     */
    public static $uiConfig = [
        'deepLinking' => false,
        'displayOperationId' => false,
        'syntaxHighlight' => [
            'theme' => 'tomorrow-night', //agate or arta or monokai or nord" or obsidian or tomorrow-night
        ],
        'filter' => true, //null or a string to filter by
        'validatorUrl' => null //disables validation change to "https://validator.swagger.io/validator" to enable
    ];

    /**
     * @var array mapping PHP types to JS
     */
    public static $dataTypeAlias = [
        'string' => 'string',
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

    protected static $tagger = TagByBasePath::class;

    protected $tags = [];
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

    /**
     * @var AuthenticationInterface[]
     */
    private $authClasses = [];

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
     * @param Tagger $tagger
     */
    public static function setTagger(Tagger $tagger): void
    {
        self::$tagger = $tagger;
    }

    /**
     * Serve static files for explorer
     * @throws HttpException
     */
    public function index(): ResponseInterface
    {
        $path = $this->request->getUri()->withQuery('')->getPath();
        if (!empty($path) && !Text::endsWith($path, '/')) {
            throw new Redirect((string)$this->request->getUri()->withPath($path . '/'));
        }
        return $this->get('index.html');
    }

    /**
     * @param $filename
     * @return ResponseInterface
     * @throws HttpException
     *
     * @url GET {filename}
     */
    public function get($filename): ResponseInterface
    {
        $filename = str_replace(['../', './', '\\', '..', '.php'], '', $filename);
        if (empty($filename)) {
            $filename = 'index.html';
        } elseif ('oauth2-redirect' == $filename || 'documentation' == $filename) {
            $filename .= '.html';
        }
        $file = __DIR__ . '/client/' . $filename;
        return PassThrough::file($file, $this->request->getHeaderLine('If-Modified-Since'));
    }

    public function config()
    {
        return (object)static::$uiConfig;
    }

    /**
     * @return object
     */
    public function docs(): stdClass
    {
        $s = new stdClass();
        $s->openapi = static::OPEN_API_SPEC_VERSION;

        $r = $this->restler;
        if (Defaults::$useUrlBasedVersioning) {
            $s->info = $this->info(Router::$maximumVersion);
            $s->servers = $this->servers();
            $s->paths = [];
            for (
                $version = max(Router::$minimumVersion, $r->requestedApiVersion);
                $version <= Router::$maximumVersion;
                $version++
            ) {
                $paths = $this->paths($version);
                foreach ($paths as $path => $value) {
                    $s->paths[1 === $version ? $path : "/v$version{$path}"] = $value;
                }
            }
        } else {
            $version = $r->requestedApiVersion;
            $s->info = $this->info($version);
            $s->servers = $this->servers();
            $s->paths = $this->paths($version);
        }

        $s->components = $this->components();
        $s->tags = [];
        foreach ($this->tags as $name => $description) {
            $s->tags[] = compact('name', 'description');
        }
        return $s;
    }

    private function info(int $version): array
    {
        $info = array_filter(call_user_func(static::$infoClass . '::format', static::OPEN_API_SPEC_VERSION));
        $info['description'] .= '<p>Api Documentation - [ReDoc](' . dirname(
                $this->request->getUri()
            ) . '/documentation.html)</p>';
        $info['version'] = (string)$version;
        return $info;
    }

    /**
     * @return array
     */
    private function servers(): array
    {
        return empty(static::$servers)
            ? [
                [
                    'url' => (string)$this->restler->baseUrl,
                    //'description' => $this->restler->baseUrl->getHost() ?? 'server'
                ]
            ]
            : static::$servers;
    }

    /**
     * @param int $version
     * @return array
     */
    private function paths(int $version = 1): array
    {
        $self = explode('/', $this->route->path);
        array_pop($self);
        $self = implode('/', $self);
        $selfExclude = empty($self) ? ['', '{s0}', 'docs', 'config'] : [$self];
        $map = Router::findAll(
            $this->request,
            [$this->restler, 'make'],
            array_merge(static::$excludedPaths, $selfExclude),
            static::$excludedHttpMethods,
            $version
        );
        $paths = [];
        foreach ($map as $path => $data) {
            foreach ($data as $item) {
                /** @var Route $route */
                $route = $item['route'];
                $access = $item['access'];
                $this->authClasses = array_merge($this->authClasses, $route->authClasses);
                if (static::$hideProtected && !$access) {
                    continue;
                }
                $url = $route->url;
                $paths["/$url"][strtolower($route->httpMethod)] = $this->operation($route, $version);
            }
        }
        $this->authClasses = array_unique($this->authClasses);
        return $paths;
    }

    private function operation(Route $route, int $version): stdClass
    {
        $r = new stdClass();
        $r->operationId = $this->operationId($route, $version);
        $tags = call_user_func([static::$tagger, 'tags'], $route, $version);
        $r->tags = array_keys($tags);
        $this->tags = array_merge($tags, $this->tags);
        [$r->parameters, $r->requestBody] = $this->parameters($route, $version);

        if (is_null($r->requestBody)) {
            unset($r->requestBody);
        }
        if (Route::ACCESS_PUBLIC !== $route->access) {
            foreach ($route->authClasses as $authClass) {
                $r->security[][ClassName::short($authClass)] = [];
            }
        }
        $r->summary = $route->summary ?? '';
        $r->description = $route->description ?? '';
        $r->responses = $this->responses($route);
        $r->deprecated = $route->deprecated;
        return $r;
    }

    private function operationId(Route $route, int $version, bool $asClassName = false)
    {
        static $hash = [];
        $id = sprintf("%s v%d/%s", $route->httpMethod, $version, $route->url);
        if (isset($hash[$id])) {
            return $hash[$id][$asClassName];
        }

        if (is_array($route->action) && 2 == count($route->action) && is_string($route->action[0])) {
            $class = ClassName::short($route->action[0]);
            $method = $route->action[1];
            if (isset(static::$prefixes[$method])) {
                $method = static::$prefixes[$method] . $class;
            } else {
                $method = str_ireplace(
                    array_keys(static::$prefixes),
                    array_values(static::$prefixes),
                    $method
                );
                $method = lcfirst($class) . ucfirst($method);
            }
            $hash[$id] = [$id, $method];
            return $hash[$id][$asClassName];
        }

        $hash[$id] = [$id, Text::slug($id, '')];
        return $hash[$id][$asClassName];
    }

    private function parameters(Route $route, int $version): array
    {
        $parameters = $route->filterParams(false);
        $body = $route->filterParams(true);
        $bodyValues = array_values($body);
        $r = [];
        $requestBody = null;
        foreach ($parameters as $param) {
            $r[] = $this->parameter($param, $param->description ?? '');
        }
        if (!empty($body)) {
            if (
                1 == count($bodyValues) &&
                (static::$allowScalarValueOnRequestBody || !empty($bodyValues[0]->children))
            ) {
                $requestBody = $this->requestBody($route, $bodyValues[0]);
            } else {
                //lets group all body parameters under a generated model name
                $name = $this->modelName($route, $version);
                $requestBody = $this->requestBody(
                    $route,
                    Param::__set_state(
                        [
                            'name' => $name,
                            'type' => $name,
                            'scalar' => false,
                            'multiple' => false,
                            'from' => 'body',
                            'required' => true,
                            'properties' => $body,
                        ]
                    )
                );
            }
        }
        return [$r, $requestBody];
    }

    private function parameter(Param $param, $description = '')
    {
        $p = (object)[
            'name' => $param->name ?? '',
            'in' => $param->from,
            'description' => $description,
            'required' => $param->required,
            'schema' => new stdClass(),
        ];

        $this->setProperties($param, $p->schema);

        if (isset($param->rules['example'])) {
            $p->examples = [1 => ['value' => $param->rules['example']]];
        }

        return $p;
    }

    private function setProperties(Type $param, stdClass $schema)
    {
        //primitives
        if ($param->scalar) {
            if ($param->multiple) {
                $schema->type = 'array';
                $schema->items = new stdClass;
                $this->scalarProperties($schema->items, $param);
            } else {
                $this->scalarProperties($schema, $param);
            }
            //TODO: $p->items and $p->uniqueItems boolean
        } elseif ('array' === $param->type) {
            if ('associative' == $param->format) {
                $schema->type = 'object';
            } else { //'indexed == $param->format
                $schema->type = 'array';
                $schema->items = new stdClass;
            }
        } else {
            $target = $schema;
            if ($param->multiple) {
                $schema->type = 'array';
                $schema->items = new stdClass;
                $target = $schema->items;
            }
            if ($param->type === UploadedFileInterface::class) {
                $target->type = 'string';
                $target->format = 'binary';
                return;
            }
            $target->type = 'object';
            if (!empty($param->properties)) {
                $target->properties = new stdClass;
                foreach ($param->properties as $name => $child) {
                    $sch = $target->properties->{$name} = new stdClass();
                    $this->setProperties($child, $sch);
                }
            }
        }
    }

    private function scalarProperties(stdClass $s, Type $param)
    {
        if ($t = static::$dataTypeAlias[$param->type] ?? null) {
            is_array($t) ? [$s->type, $s->format] = $t : $s->type = $t;
        } else {
            $s->type = $param->type;
        }
        if (is_array($t)) {
            $s->type = $t[0];
            $s->format = $t[1];
        } elseif (is_string($t)) {
            $s->type = $t;
        }
        $has64bit = PHP_INT_MAX > 2147483647;
        if ($s->type == 'integer') {
            $s->format = $has64bit
                ? 'int64'
                : 'int32';
        } elseif ($s->type == 'number') {
            $s->format = $has64bit
                ? 'double'
                : 'float';
        }
        if (!$param instanceof Param) {
            return;
        }
        if ($param->default[0]) {
            $s->default = $param->default[1];
        }
        if ($param->choice) {
            $s->enum = $param->choice;
        }
        if ($param->min) {
            $s->minimum = $param->min;
        }
        if ($param->max) {
            $s->maximum = $param->max;
        }
    }

    private function requestBody(Route $route, Param $param, $description = '')
    {
        $p = $this->parameter($param, $description);
        $content = [];
        foreach ($route->requestMediaTypes as $mime) {
            $content[$mime] = ['schema' => $p->schema];
        }
        $this->requestBodies[$param->type] = compact('content');
        return (object)['$ref' => "#/components/requestBodies/{$param->type}"];
    }

    private function modelName(Route $route, int $version): string
    {
        return ucfirst($this->operationId($route, $version, true)) . 'Model';
    }

    /**
     * @param Route $route
     * @return array[]
     * @throws HttpException
     * @throws ReflectionException
     */
    private function responses(Route $route): array
    {
        $code = '200';
        if (isset($route->status)) {
            $code = $route->status;
        }
        $schema = new stdClass();
        $content = [];
        foreach ($route->responseMediaTypes as $mime) {
            $mediaType = $route->responseFormatMap[$mime];
            if (TypeUtil::implements($mediaType, DownloadableFileMediaTypeInterface::class)) {
                $content[$mime] = ['schema' => (object)['type' => 'string', 'format' => 'binary']];
                continue;
            }
            $content[$mime] = ['schema' => $schema];
        }
        $r = [
            $code => [
                'description' => HttpException::$codes[$code] ?? 'Success',
                'content' => $content,
            ],
        ];
        if ($route->return) {
            $this->setProperties($route->return, $schema);
        }

        if (is_array($throws = $route->throws ?? null)) {
            /** @var ComposerInterface $composer */
            $composer = ClassName::get(ComposerInterface::class);
            foreach ($throws as $throw) {
                $r[$throw['code']] = ['description' => $throw['message']];
                $content = [];
                foreach ($route->requestMediaTypes as $mime) {
                    $schema = new stdClass();
                    $class = $composer::errorResponseClass($throw['code'], $mime);
                    $content[$mime] = ['schema' => $schema];
                    $this->setProperties(Returns::fromClass(new ReflectionClass($class)), $schema);
                }
                $r[$throw['code']]['content'] = $content;
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
        foreach ($this->authClasses as $class) {
            if (TypeUtil::matches($class, ExplorableAuthenticationInterface::class)) {
                $schemes[ClassName::short($class)] = (object)$class::scheme()->toArray(
                    $this->restler->baseUrl->getPath() . '/'
                );
            }
        }
        return (object)$schemes;
    }
}
