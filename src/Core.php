<?php namespace Luracast\Restler;

use ArrayAccess;
use Exception;
use Luracast\Restler\Contracts\{
    AuthenticationInterface,
    ComposerInterface,
    ContainerInterface,
    FilterInterface,
    RequestMediaTypeInterface,
    ResponseMediaTypeInterface,
    SelectivePathsInterface,
    UsesAuthenticationInterface,
    ValidationInterface
};
use Luracast\Restler\Data\{Param, Route};
use Luracast\Restler\Exceptions\{HttpException, InvalidAuthCredentials};
use Luracast\Restler\MediaTypes\{Json, UrlEncoded, Xml};
use Luracast\Restler\Utils\{ClassName, Header, Text, Validator};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use React\Promise\PromiseInterface;
use ReflectionException;
use Throwable;
use TypeError;

/**
 * @property UriInterface baseUrl
 * @property string path
 * @property bool authenticated
 * @property bool authVerified
 * @property int requestedApiVersion
 * @property string requestMethod
 * @property Route route
 * @property HttpException exception
 * @property ResponseHeaders responseHeaders
 * @property int responseCode
 */
abstract class Core
{
    const VERSION = '4.0.0';

    protected $_authenticated = false;
    protected $_authVerified = false;
    /**
     * @var int
     */
    public $requestedApiVersion = 1;

    protected $_requestMethod = 'GET';
    /**
     * @var bool
     */
    protected $requestFormatDiffered = false;
    /**
     * @var Route
     */
    protected $_route;
    /**
     * @var ResponseMediaTypeInterface
     */
    public $responseFormat;
    protected $_path = '';
    /**
     * @var RequestMediaTypeInterface
     */
    public $requestFormat;
    protected $body = [];
    protected $query = [];

    /**
     * @var ResponseHeaders
     */
    protected $_responseHeaders = null;
    protected $_responseCode = null;
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var StaticProperties
     */
    protected $config;
    /**
     * @var StaticProperties
     */
    protected $defaults;
    /**
     * @var StaticProperties
     */
    protected $router;
    /**
     * @var int for calculating execution time
     */
    protected $startTime;
    /** @var UriInterface */
    private $_baseUrl;
    /** @var HttpException */
    protected $_exception;

    /**
     * Core constructor.
     * @param ContainerInterface $container
     * @param array|ArrayAccess $config
     * @throws TypeError
     */
    public function __construct(ContainerInterface $container = null, &$config = [])
    {
        if (!is_array($config) && !$config instanceof ArrayAccess) {
            throw new TypeError('Argument 2 passed to ' . __CLASS__
                . '::__construct() must be an array or implement ArrayAccess');
        }

        $this->startTime = time();
        $this->_responseHeaders = new ResponseHeaders();

        if (is_null($config)) {
            $config = new ArrayObject();
        }
        $this->config = &$config;

        $this->config['defaults'] = $this->defaults = new StaticProperties(Defaults::class);
        $this->config['router'] = $this->router = new StaticProperties(Router::class);

        if ($container) {
            $container->init($config);
        } else {
            $container = new Container($config);
        }
        $container->instance(Core::class, $this);
        $container->instance(static::class, $this);
        $container->instance(ContainerInterface::class, $container);
        $container->instance(get_class($container), $container);
        $container->instance(ResponseHeaders::class, $this->_responseHeaders);
        $this->container = $container;
    }


    public function make($className, Route $route = null, bool $recreate = false)
    {
        $properties = [];
        $fullName = $className;
        if (!$route) {
            $route = $this->_route;
        }
        if ($route && property_exists($route, 'set')) {
            $shortName = ClassName::short($fullName);
            $properties = $route->set[$fullName] ?? $route->set[$shortName] ?? [];
            $name = lcfirst($shortName);
            if (!isset($this->config[$name])) {
                $this->config[$name] = new StaticProperties($fullName);;
            }
            foreach ($properties as $property => $value) {
                if (isset($this->config[$name][$property])) {
                    $this->config[$name][$property] = $value;
                }
            }
        }
        if ($recreate) {
            //delete existing instance if any
            $this->container->instance($className, null);
        }

        $instance = $this->container->make($className);
        $objectVars = get_object_vars($instance);
        foreach ($properties as $property => $value) {
            if (property_exists($fullName, $property) && array_key_exists($property, $objectVars)) {
                //if not a static property
                $instance->{$property} = $value;
            }
        }
        if ($instance instanceof UsesAuthenticationInterface) {
            $instance->_setAuthenticationStatus($this->_authenticated, $this->_authVerified);
        }

        return $instance;
    }

    abstract protected function get(): void;

    protected function getPath(UriInterface $uri, string $scriptName = ''): string
    {
        $slash = '/';
        $path = $uri->getPath();
        $path = str_replace(
            array_merge(
                $this->router->responseFormatMap['extensions'],
                $this->router->responseFormatOverridesMap['extensions']
            ),
            '',
            trim($path, $slash)
        );
        $fullPath = $path;
        if (empty($scriptName)) {
            $this->_baseUrl = $uri->withPath('')->withQuery('');
        } else {
            $path = Text::removeCommon($path, ltrim($scriptName, $slash));
            $this->_baseUrl = $uri
                ->withPath(rtrim(substr($fullPath, 0, -strlen($path)), $slash))
                ->withQuery('');
        }
        if (Defaults::$useUrlBasedVersioning && strlen($path) && $path[0] == 'v') {
            $version = intval(substr($path, 1));
            if ($version && $version <= $this->router->maximumVersion) {
                $this->requestedApiVersion = $version;
                $path = explode($slash, $path, 2);
                $path = count($path) == 2 ? $path[1] : '';
            }
        } else {
            $this->requestedApiVersion = $this->router->minimumVersion;
        }
        return $path;
    }

    /**
     * @param array $get
     * @return array
     * @throws Exception
     */
    protected function getQuery(array $get = []): array
    {
        $get = UrlEncoded::decoderTypeFix($get);
        //apply app property changes
        foreach ($get as $key => $value) {
            if ($alias = $this->defaults->fromQuery[$key] ?? false) {
                $this->changeAppProperty($alias, $value);
            }
        }
        return $get;
    }

    /**
     * @param string $contentType
     * @return RequestMediaTypeInterface
     * @throws HttpException
     */
    protected function getRequestMediaType(string $contentType): RequestMediaTypeInterface
    {
        /** @var RequestMediaTypeInterface $format */
        $format = null;
        // check if client has sent any information on request format
        if (!empty($contentType)) {
            //remove charset if found
            $mime = strtok($contentType, ';');
            if ($mime == UrlEncoded::MIME) {
                $format = $this->make(UrlEncoded::class, null);
            } elseif (isset($this->router->requestFormatMap[$mime])) {
                $format = $this->make($this->router->requestFormatMap[$mime], null);
                $format->mediaType($mime);
            } elseif (!$this->requestFormatDiffered && isset($this->router->requestFormatOverridesMap[$mime])) {
                //if our api method is not using an @format comment
                //to point to this $mime, we need to throw 403 as in below
                //but since we don't know that yet, we need to defer that here
                $format = $this->make($this->router->requestFormatOverridesMap[$mime], null);
                $format->mediaType($mime);
                $this->requestFormatDiffered = true;
            } else {
                throw new HttpException(
                    403,
                    "Content type `$mime` is not supported."
                );
            }
        }
        if (!$format) {
            $format = $this->make($this->router->requestFormatMap['default'], null);
        }
        return $format;
    }

    protected function getBody(string $raw = ''): array
    {
        $r = [];
        if ($this->_requestMethod == 'PUT'
            || $this->_requestMethod == 'PATCH'
            || $this->_requestMethod == 'POST'
        ) {
            $r = $this->requestFormat->decode($raw);

            $r = is_array($r)
                ? array_merge($r, [$this->defaults->fullRequestDataName => $r])
                : [$this->defaults->fullRequestDataName => $r];
        }
        return $r;
    }

    public function getRequestData(): array
    {
        return $this->body + $this->query;
    }

    /**
     * @throws HttpException
     * @throws Exception
     */
    protected function route(): void
    {
        $this->_route = $o = Router::find(
            $this->_path,
            $this->_requestMethod,
            $this->requestedApiVersion,
            $this->body + $this->query
        );
        $this->container->instance(Route::class, $o);
        //set defaults based on api method comments
        foreach ($this->defaults->fromComments as $key => $property) {
            if (property_exists($o, $key)) {
                $value = $o->{$key};
                $this->changeAppProperty($property, $value);
            }
        }
        if (!isset($o->action)) {
            throw new HttpException(404);
        }
    }

    abstract protected function negotiate(): void;

    /**
     * @param string $path
     * @param string $acceptHeader
     * @return ResponseMediaTypeInterface
     * @throws HttpException
     * @throws Exception
     */
    protected function negotiateResponseMediaType(string $path, string $acceptHeader = ''): ResponseMediaTypeInterface
    {
        $map = $this->_route->responseFormatMap ?? $this->router->responseFormatMap;
        // check if client has specified an extension
        /** @var $format ResponseMediaTypeInterface */
        $format = null;
        $extensions = explode('.', parse_url($path, PHP_URL_PATH));
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset($map[$extension])) {
                $format = $this->make($map[$extension], null);
                $format->extension($extension);
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if (!empty($acceptHeader)) {
            $acceptList = Header::sortByPriority($acceptHeader);
            foreach ($acceptList as $accept => $quality) {
                if (isset($map[$accept])) {
                    $format = $this->make($map[$accept], null);
                    $format->mediaType($accept);
                    // Tell cache content is based on Accept header
                    $this->_responseHeaders['Vary'] = 'Accept';
                    return $format;

                } elseif (false !== ($index = strrpos($accept, '+'))) {
                    $mime = substr($accept, 0, $index);
                    $vendor = 'application/vnd.'
                        . $this->defaults->apiVendor . '-v';
                    if (is_string($this->defaults->apiVendor) && 0 === stripos($mime, $vendor)) {
                        $extension = substr($accept, $index + 1);
                        if (isset($map[$extension])) {
                            //check the MIME and extract version
                            $version = intval(substr($mime, strlen($vendor)));

                            if ($version >= $this->router->minimumVersion &&
                                $version <= $this->router->maximumVersion) {

                                $this->requestedApiVersion = $version;
                                $format = $this->make($map[$extension], null);
                                $format->mediaType("$vendor$version+$extension");
                                if (is_null($this->defaults->useVendorMIMEVersioning)) {
                                    $this->defaults->useVendorMIMEVersioning = true;
                                }
                                $this->_responseHeaders['Vary'] = 'Accept';
                                return $format;
                            }
                        }
                    }

                }
            }
        } else {
            // RFC 2616: If no Accept header field is
            // present, then it is assumed that the
            // client accepts all media types.
            $acceptHeader = '*/*';
        }
        if (strpos($acceptHeader, '*') !== false) {
            if (false !== strpos($acceptHeader, 'application/*')) {
                $format = $this->make(Json::class, null);
            } elseif (false !== strpos($acceptHeader, 'text/*')) {
                $format = $this->make(Xml::class, null);
            } elseif (false !== strpos($acceptHeader, '*/*')) {
                $format = $this->make($map['default'], null);
            }
        }
        if (empty($format)) {
            // RFC 2616: If an Accept header field is present, and if the
            // server cannot send a response which is acceptable according to
            // the combined Accept field value, then the server SHOULD send
            // a 406 (not acceptable) response.
            $format = $this->make($map['default'], null);
            $this->responseFormat = $format;
            throw new HttpException(
                406,
                'Content negotiation failed. ' .
                'Try `' . $format->mediaType() . '` instead.'
            );
        } else {
            // Tell cache content is based at Accept header
            $this->_responseHeaders['Vary'] = 'Accept';
            return $format;
        }
    }

    /**
     * @param string $requestMethod
     * @param string $accessControlRequestMethod
     * @param string $accessControlRequestHeaders
     * @param string $origin
     * @throws HttpException
     */
    protected function negotiateCORS(
        string $requestMethod,
        string $accessControlRequestMethod = '',
        string $accessControlRequestHeaders = '',
        string $origin = ''
    ): void
    {
        if (!$this->defaults->crossOriginResourceSharing || $requestMethod != 'OPTIONS') {
            return;
        }
        if (!empty($accessControlRequestMethod)) {
            $this->_responseHeaders['Access-Control-Allow-Methods'] = $this->defaults->accessControlAllowMethods;
        }
        if (!empty($accessControlRequestHeaders)) {
            $this->_responseHeaders['Access-Control-Allow-Headers'] = $accessControlRequestHeaders;
        }
        $e = new HttpException(200);
        $e->emptyMessageBody = true;
        throw $e;
    }

    /**
     * @param string $acceptCharset
     * @throws HttpException
     */
    protected function negotiateCharset(string $acceptCharset = '*'): void
    {
        if (!empty($acceptCharset)) {
            $found = false;
            $charList = Header::sortByPriority($acceptCharset);
            foreach ($charList as $charset => $quality) {
                if (in_array($charset, (array)$this->defaults->supportedCharsets)) {
                    $found = true;
                    $this->defaults->charset = $charset;
                    break;
                }
            }
            if (!$found) {
                if (strpos($acceptCharset, '*') !== false) {
                    //use default charset
                } else {
                    throw new HttpException(
                        406,
                        'Content negotiation failed. ' .
                        'Requested charset is not supported'
                    );
                }
            }
        }
    }

    protected function negotiateLanguage(string $acceptLanguage = ''): void
    {
        if (!empty($acceptLanguage)) {
            $found = false;
            $langList = Header::sortByPriority($acceptLanguage);
            foreach ($langList as $lang => $quality) {
                foreach ($this->defaults->supportedLanguages as $supported) {
                    if (strcasecmp($supported, $lang) == 0) {
                        $found = true;
                        $this->defaults->language = $supported;
                        break 2;
                    }
                }
            }
            if (!$found) {
                if (strpos($acceptLanguage, '*') !== false) {
                    //use default language
                } /** @noinspection PhpStatementHasEmptyBodyInspection */ else {
                    //ignore for now! //TODO: find best response for language negotiation failure
                }
            }
        }
    }

    /**
     * Filer api calls before authentication
     * @param ServerRequestInterface $request
     * @param bool $postAuth
     * @throws HttpException
     */
    protected function filter(ServerRequestInterface $request, bool $postAuth = false)
    {
        $name = $postAuth ? 'postAuthFilterClasses' : 'preAuthFilterClasses';
        foreach ($this->router->{$name} as $i => $filerClass) {
            //exclude invalid paths
            if (!static::isPathSelected($filerClass, $this->_path)) {
                continue;
            }
            /** @var FilterInterface $filter */
            $filter = $this->make($filerClass, null);
            if (!$filter->_isAllowed($request, $this->_responseHeaders)) {
                throw new HttpException(403);
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @throws HttpException
     * @throws InvalidAuthCredentials
     * @throws Exception
     */
    protected function authenticate(ServerRequestInterface $request)
    {
        $o = &$this->_route;
        $accessLevel = max($this->defaults->apiAccessLevel, $o->access);
        if ($accessLevel) {
            if (!count($this->router->authClasses) && $accessLevel > 1) {
                throw new HttpException(
                    403,
                    'at least one Authentication Class is required'
                );
            }
            $unauthorized = false;
            $authClasses = $this->router->authClasses;
            foreach ($authClasses as $i => $authClass) {
                try {
                    //exclude invalid paths
                    if (!static::isPathSelected($authClass, $this->_path)) {
                        array_splice($this->router->authClasses, $i, 1);
                        continue;
                    }
                    /** @var AuthenticationInterface $auth */
                    $auth = $this->make($authClass, null);
                    if (!$auth->_isAllowed($request, $this->_responseHeaders)) {
                        throw new HttpException(401);
                    }
                    $unauthorized = false;
                    //make this auth class as the first one
                    array_splice($this->router->authClasses, $i, 1);
                    array_unshift($this->router->authClasses, $authClass);
                    break;
                } catch (InvalidAuthCredentials $e) { //provided credentials does not authenticate
                    $this->_authenticated = false;
                    throw $e;
                } catch (HttpException $e) {
                    if (!$unauthorized) {
                        $unauthorized = $e;
                    }
                }
            }
            //when none of the auth classes apply and it's not a hybrid api
            if (!count($this->router->authClasses) && $accessLevel > 1) {
                throw new HttpException(
                    403,
                    'at least one Authentication Class should apply to path `' . $this->_path . '`'
                );
            }
            $this->_authVerified = true;
            if ($unauthorized) {
                if ($accessLevel > 1) { //when it is not a hybrid api
                    throw $unauthorized;
                } else {
                    $this->_authenticated = false;
                }
            } else {
                $this->_authenticated = true;
            }
        }
    }

    /**
     *
     */
    protected function validate()
    {
        if (!$this->defaults->autoValidationEnabled) {
            return;
        }
        $validator = $this->make(ValidationInterface::class, null);
        $this->_route->validate($validator, [$this, 'make']);
    }

    /**
     * @param Route $route
     * @return mixed
     * @throws ReflectionException
     */
    public function call(Route $route)
    {
        $access = max($this->defaults->apiAccessLevel, $route->access);
        return $route->handle($access, [$this, 'make']);
    }

    abstract protected function compose($response = null);


    /**
     * @param Route|null $route
     * @param string $origin
     * @param HttpException|null $e
     */
    protected function composeHeaders(?Route $route, string $origin = '', HttpException $e = null): void
    {
        //only GET method should be cached if allowed by API developer
        $expires = $this->_requestMethod == 'GET' ? $this->defaults->headerExpires : 0;
        $headerCacheControl = $this->defaults->headerCacheControl;
        if (is_string($headerCacheControl)) {
            $headerCacheControl = [$headerCacheControl];
        }
        $cacheControl = $headerCacheControl[0];
        if ($expires > 0) {
            $cacheControl = $this->_route->access ? 'private, ' : 'public, ';
            $cacheControl .= end($headerCacheControl);
            $cacheControl = str_replace('{expires}', $expires, $cacheControl);
            $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $expires);
        }
        $this->_responseHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', $this->startTime);
        $this->_responseHeaders['Cache-Control'] = $cacheControl;
        $this->_responseHeaders['Expires'] = $expires;
        $this->_responseHeaders['X-Powered-By'] = 'Luracast Restler v' . static::VERSION;

        if ($this->defaults->crossOriginResourceSharing) {
            if (!empty($origin)) {
                $this->_responseHeaders['Access-Control-Allow-Origin']
                    = $this->defaults->accessControlAllowOrigin == '*'
                    ? $origin
                    : $this->defaults->accessControlAllowOrigin;
                $this->_responseHeaders['Access-Control-Allow-Credentials'] = 'true';
                $this->_responseHeaders['Access-Control-Max-Age'] = 86400;
            } elseif ($this->_requestMethod == 'OPTIONS') {
                $this->_responseHeaders['Access-Control-Allow-Origin']
                    = $this->defaults->accessControlAllowOrigin;
                $this->_responseHeaders['Access-Control-Allow-Credentials'] = 'true';
            }
        }
        $this->_responseHeaders['Content-Language'] = $this->defaults->language;

        if (isset($route->header)) {
            foreach ($route->header as $header) {
                $parts = explode(': ', $header, 2);
                $this->_responseHeaders[$parts[0]] = $parts[1];
            }
        }

        $code = 200;
        if (!$this->defaults->suppressResponseCode) {
            if ($e) {
                $code = $e->getCode();
            } elseif (!$this->_responseCode && isset($route->status)) {
                $code = $route->status;
            }
        }
        $this->_responseCode = $code;
        $this->responseFormat->charset($this->defaults->charset);
        $charset = $this->responseFormat->charset()
            ?: $this->defaults->charset;

        $this->_responseHeaders['Content-Type'] =
            $this->responseFormat->mediaType() . "; charset=$charset";
        if ($e && $e instanceof HttpException) {
            foreach ($e->getHeaders() as $key => $value) {
                $this->_responseHeaders[$key] = $value;
            }
        }
    }

    protected function message(Throwable $e, string $origin)
    {
        if (!$this->responseFormat) {
            $this->responseFormat = new Json();
        }
        if (!$e instanceof HttpException) {
            $e = new HttpException(500, $e->getMessage(), [], $e);
        }
        $this->_responseCode = $e->getCode();
        $this->composeHeaders(
            $this->_route,
            $origin,
            $e
        );
        if ($e->emptyMessageBody) {
            return null;
        }
        /** @var ComposerInterface $compose */
        $compose = $this->make(ComposerInterface::class, null);
        return $compose->message($e);
    }

    abstract protected function respond($response = []): ResponseInterface;

    abstract protected function stream($data): ResponseInterface;

    abstract public function handle(ServerRequestInterface $request = null): PromiseInterface;

    private static function isPathSelected(string $class, string $path): bool
    {
        if (!isset(class_implements($class)[SelectivePathsInterface::class])) {
            return true;
        }
        return $class::isPathSelected($path);
    }

    public function __get($name)
    {
        if (property_exists($this, '_' . $name)) {
            return $this->{'_' . $name};
        }
        return null;
    }

    /**
     * Change app property from a query string or comments
     *
     * @param $property
     * @param $value
     *
     * @return bool
     *
     * @throws Exception
     */
    private function changeAppProperty($property, $value)
    {
        if (!property_exists(Defaults::class, $property)) {
            return false;
        }
        if ($detail = Defaults::$propertyValidations[$property] ?? false) {
            /** @noinspection PhpParamsInspection */
            $value = Validator::validate($value, Param::parse($detail));
        }
        $this->defaults->{$property} = $value;
        return true;
    }
}
