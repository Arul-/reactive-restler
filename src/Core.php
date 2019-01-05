<?php namespace Luracast\Restler;

use ArrayAccess;
use Exception;
use Luracast\Restler\Contracts\{AuthenticationInterface,
    ComposerInterface,
    ContainerInterface,
    FilterInterface,
    RequestMediaTypeInterface,
    ResponseMediaTypeInterface,
    SelectivePathsInterface,
    UsesAuthenticationInterface,
    ValidationInterface};
use Luracast\Restler\Exceptions\{HttpException, InvalidAuthCredentials};
use Luracast\Restler\MediaTypes\{Json, UrlEncoded, Xml};
use Luracast\Restler\Utils\{ApiMethodInfo, ClassName, CommentParser, Header, Text, ValidationInfo, Validator};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use React\Promise\PromiseInterface;
use ReflectionException;
use ReflectionMethod;
use Throwable;
use TypeError;

/**
 * @property UriInterface baseUrl
 * @property string path
 * @property bool authenticated
 * @property bool authVerified
 * @property int requestedApiVersion
 * @property string requestMethod
 * @property ApiMethodInfo apiMethodInfo
 * @property HttpException exception
 * @property array responseHeaders
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
     * @var ApiMethodInfo
     */
    protected $_apiMethodInfo;
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

    protected $_responseHeaders = [];
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

        $config = &$config ?? new ArrayObject();
        $this->config = &$config;

        $this->config['defaults'] = $this->defaults = StaticProperties::forClass(Defaults::class);
        $this->config['router'] = $this->router = StaticProperties::forClass(Router::class);

        if ($container) {
            $container->init($config);
        } else {
            $container = new Container($config);
        }
        $container->instance(Core::class, $this);
        $container->instance(static::class, $this);
        $container->instance(ContainerInterface::class, $container);
        $container->instance(get_class($container), $container);
        $this->container = $container;
    }


    public function make($className)
    {
        $properties = [];
        $fullName = $className;
        if ($m = $this->_apiMethodInfo->metadata ?? false) {
            $shortName = ClassName::short($fullName);
            $properties = $m['class'][$fullName][CommentParser::$embeddedDataName] ??
                $m['class'][$shortName][CommentParser::$embeddedDataName] ?? [];
            $name = lcfirst($shortName);
            if (!isset($this->config[$name])) {
                $this->config[$name] = StaticProperties::forClass($fullName);;
            }
            foreach ($properties as $property => $value) {
                if (isset($this->config[$name][$property])) {
                    $this->config[$name][$property] = $value;
                }
            }
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
            $instance->__setAuthenticationStatus($this->_authenticated, $this->_authVerified);
        }

        return $instance;
    }

    abstract protected function get(): void;

    protected function getPath(UriInterface $uri, string $scriptName = ''): string
    {
        $path = Text::removeCommon($uri->getPath(), $scriptName);
        $path = str_replace(
            array_merge(
                $this->router['responseFormatMap']['extensions']->getArrayCopy(),
                $this->router['formatOverridesMap']['extensions']->getArrayCopy()
            ),
            '',
            trim($path, '/')
        );
        $url = (string)$uri;
        $url = strtok($url, '.?');
        $uriClass = get_class($uri);
        $this->_baseUrl = new $uriClass(substr($url, 0, -strlen($path)));
        if (Defaults::$useUrlBasedVersioning && strlen($path) && $path{0} == 'v') {
            $version = intval(substr($path, 1));
            if ($version && $version <= $this->router['maximumVersion']) {
                $this->requestedApiVersion = $version;
                $path = explode('/', $path, 2);
                $path = count($path) == 2 ? $path[1] : '';
            }
        } else {
            $this->requestedApiVersion = $this->router['minimumVersion'];
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
            if ($alias = $this->defaults['fromQuery'][$key] ?? false) {
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
                $format = $this->make(UrlEncoded::class);
            } elseif (isset($this->router['requestFormatMap'][$mime])) {
                $format = $this->make($this->router['requestFormatMap'][$mime]);
                $format->mediaType($mime);
            } elseif (!$this->requestFormatDiffered && isset($this->router['formatOverridesMap'][$mime])) {
                //if our api method is not using an @format comment
                //to point to this $mime, we need to throw 403 as in below
                //but since we don't know that yet, we need to defer that here
                $format = $this->make($this->router['formatOverridesMap'][$mime]);
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
            $format = $this->make($this->router['requestFormatMap']['default']);
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
                ? array_merge($r, array($this->defaults['fullRequestDataName'] => $r))
                : array($this->defaults['fullRequestDataName'] => $r);
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
        $this->_apiMethodInfo = $o = Router::find(
            $this->_path,
            $this->_requestMethod,
            $this->requestedApiVersion,
            $this->body + $this->query
        );
        $this->container->instance(ApiMethodInfo::class, $o);
        //set defaults based on api method comments
        if (isset($o->metadata)) {
            foreach ($this->defaults['fromComments'] as $key => $property) {
                if (array_key_exists($key, $o->metadata)) {
                    $value = $o->metadata[$key];
                    $this->changeAppProperty($property, $value);
                }
            }
        }
        if (!isset($o->className)) {
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
        $readableFormats = [];
        //check if the api method insists on response format using @format comment
        if (($metadata = $this->_apiMethodInfo->metadata ?? false) && ($formats = $metadata['format'] ?? false)) {
            $formats = explode(',', (string)$formats);
            foreach ($formats as $i => $f) {
                if ($f = ClassName::resolve(trim($f), $metadata['scope'])) {
                    if (!in_array($f, $this->router->formatOverridesMap->getArrayCopy())) {
                        throw new HttpException(
                            500,
                            "Given @format is not present in overriding formats. " .
                            "Please call `Router::setOverridingResponseMediaTypes('$f');` first."
                        );
                    }
                }
                $formats[$i] = $f;
                if (is_a($f, RequestMediaTypeInterface::class, true)) {
                    $readableFormats[] = $f;
                }
            }
        }
        /** @noinspection PhpInternalEntityUsedInspection */
        Router::_setMediaTypes(RequestMediaTypeInterface::class, $readableFormats,
            $this->router['requestFormatMap'],
            $this->router['readableMediaTypes']);

        if (
            $this->requestFormatDiffered &&
            !isset($this->router['requestFormatMap'][$this->requestFormat->mediaType()])
        ) {
            throw new HttpException(
                403,
                "Content type `'.$this->requestFormat->mediaType().'` is not supported."
            );
        }
        if (is_array($formats) && count($formats)) {
            /** @noinspection PhpInternalEntityUsedInspection */
            Router::_setMediaTypes(ResponseMediaTypeInterface::class, $formats,
                $this->router['responseFormatMap'],
                $this->router['writableMediaTypes']);
        }


        // check if client has specified an extension
        /** @var $format ResponseMediaTypeInterface */
        $format = null;
        $extensions = explode('.', parse_url($path, PHP_URL_PATH));
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset($this->router['responseFormatMap'][$extension])) {
                $format = $this->make($this->router['responseFormatMap'][$extension]);
                $format->extension($extension);
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if (!empty($acceptHeader)) {
            $acceptList = Header::sortByPriority($acceptHeader);
            foreach ($acceptList as $accept => $quality) {
                if (isset($this->router['responseFormatMap'][$accept])) {
                    $format = $this->make($this->router['responseFormatMap'][$accept]);
                    $format->mediaType($accept);
                    // Tell cache content is based on Accept header
                    $this->_responseHeaders['Vary'] = 'Accept';
                    return $format;

                } elseif (false !== ($index = strrpos($accept, '+'))) {
                    $mime = substr($accept, 0, $index);
                    $vendor = 'application/vnd.'
                        . $this->defaults['apiVendor'] . '-v';
                    if (is_string($this->defaults['apiVendor']) && 0 === stripos($mime, $vendor)) {
                        $extension = substr($accept, $index + 1);
                        if (isset($this->router['responseFormatMap'][$extension])) {
                            //check the MIME and extract version
                            $version = intval(substr($mime, strlen($vendor)));

                            if ($version >= $this->router['minimumVersion'] &&
                                $version <= $this->router['maximumVersion']) {

                                $this->requestedApiVersion = $version;
                                $format = $this->make($this->router['responseFormatMap'][$extension]);
                                $format->mediaType("$vendor$version+$extension");
                                //$this->app['useVendorMIMEVersioning'] = true;
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
                $format = $this->make(Json::class);
            } elseif (false !== strpos($acceptHeader, 'text/*')) {
                $format = $this->make(Xml::class);
            } elseif (false !== strpos($acceptHeader, '*/*')) {
                $format = $this->make($this->router['responseFormatMap']['default']);
            }
        }
        if (empty($format)) {
            // RFC 2616: If an Accept header field is present, and if the
            // server cannot send a response which is acceptable according to
            // the combined Accept field value, then the server SHOULD send
            // a 406 (not acceptable) response.
            $format = $this->make($this->router['responseFormatMap']['default']);
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
    ): void {
        if (!$this->defaults['crossOriginResourceSharing'] || $requestMethod != 'OPTIONS') {
            return;
        }
        if (!empty($accessControlRequestMethod)) {
            $this->_responseHeaders['Access-Control-Allow-Methods'] = $this->defaults['accessControlAllowMethods'];
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
                if (in_array($charset, $this->defaults['supportedCharsets'])) {
                    $found = true;
                    $this->defaults['charset'] = $charset;
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
                foreach ($this->defaults['supportedLanguages'] as $supported) {
                    if (strcasecmp($supported, $lang) == 0) {
                        $found = true;
                        $this->defaults['language'] = $supported;
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
        foreach ($this->router[$name] as $i => $filerClass) {
            //exclude invalid paths
            if (!static::isPathSelected($filerClass, $this->_path)) {
                continue;
            }
            /** @var FilterInterface $filter */
            $filter = $this->make($filerClass);
            if (!$filter->__isAllowed($request, $this->_responseHeaders)) {
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
        $o = &$this->_apiMethodInfo;
        $accessLevel = max($this->defaults['apiAccessLevel'], $o->accessLevel);
        if ($accessLevel) {
            if (!count($this->router['authClasses']) && $accessLevel > 1) {
                throw new HttpException(
                    403,
                    'at least one Authentication Class is required'
                );
            }
            $unauthorized = false;
            foreach ($this->router['authClasses'] as $i => $authClass) {
                try {
                    //exclude invalid paths
                    if (!static::isPathSelected($authClass, $this->_path)) {
                        $this->router->authClasses->splice($i, 1);
                        //array_splice($this->router['authClasses'], $i, 1);
                        continue;
                    }
                    /** @var AuthenticationInterface $auth */
                    $auth = $this->make($authClass);
                    if (!$auth->__isAllowed($request, $this->_responseHeaders)) {
                        throw new HttpException(401);
                    }
                    $unauthorized = false;
                    //make this auth class as the first one
                    $this->router->authClasses->splice($i, 1);
                    $this->router->authClasses->unshift($authClass);
                    //array_splice($this->router['authClasses'], $i, 1);
                    //array_unshift($this->router['authClasses'], $authClass);
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
            if (!count($this->router['authClasses']) && $accessLevel > 1) {
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
        if (!$this->defaults['autoValidationEnabled']) {
            return;
        }

        $o = &$this->_apiMethodInfo;
        foreach ($o->metadata['param'] as $index => $param) {
            $info = &$param [CommentParser::$embeddedDataName];
            if (!isset ($info['validate'])
                || $info['validate'] != false
            ) {
                if (isset($info['method'])) {
                    $info ['apiClassInstance'] = $this->make($o->className);
                }
                //convert to instance of ValidationInfo
                $info = new ValidationInfo($param);
                //initialize validator
                /** @var ValidationInterface $validator */
                $validator = $this->make(ValidationInterface::class);
                $valid = $o->parameters[$index];
                $o->parameters[$index] = null;
                if (empty(Validator::$exceptions)) {
                    $o->metadata['param'][$index]['autofocus'] = true;
                }
                $valid = $validator::validate(
                    $valid, $info
                );
                $o->parameters[$index] = $valid;
                unset($o->metadata['param'][$index]['autofocus']);
            }
        }
    }

    /**
     * @param ApiMethodInfo $info
     * @return mixed
     * @throws ReflectionException
     */
    public function call(ApiMethodInfo $info)
    {
        $accessLevel = max($this->defaults['apiAccessLevel'], $info->accessLevel);
        $object = $this->make($info->className);
        switch ($accessLevel) {
            case 3 : //protected method
                $reflectionMethod = new ReflectionMethod(
                    $object,
                    $info->methodName
                );
                $reflectionMethod->setAccessible(true);
                $result = $reflectionMethod->invokeArgs(
                    $object,
                    $info->parameters
                );
                break;
            default :
                $result = call_user_func_array(array(
                    $object,
                    $info->methodName
                ), $info->parameters);
        }
        return $result;
    }

    abstract protected function compose($response = null);


    /**
     * @param ApiMethodInfo|null $info
     * @param string $origin
     * @param HttpException|null $e
     */
    protected function composeHeaders(?ApiMethodInfo $info, string $origin = '', HttpException $e = null): void
    {
        //only GET method should be cached if allowed by API developer
        $expires = $this->_requestMethod == 'GET' ? $this->defaults['headerExpires'] : 0;
        if (!count($this->defaults['headerCacheControl'])) {
            $this->defaults['headerCacheControl'] = [$this->defaults['headerCacheControl']];
        }
        $cacheControl = $this->defaults['headerCacheControl'][0];
        if ($expires > 0) {
            $cacheControl = !isset($this->_apiMethodInfo->accessLevel) || $this->_apiMethodInfo->accessLevel
                ? 'private, ' : 'public, ';
            $cacheControl .= end($this->defaults['headerCacheControl']);
            $cacheControl = str_replace('{expires}', $expires, $cacheControl);
            $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $expires);
        }
        $this->_responseHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', $this->startTime);
        $this->_responseHeaders['Cache-Control'] = $cacheControl;
        $this->_responseHeaders['Expires'] = $expires;
        $this->_responseHeaders['X-Powered-By'] = 'Luracast Restler v' . static::VERSION;

        if ($this->defaults['crossOriginResourceSharing']) {
            if (!empty($origin)) {
                $this->_responseHeaders['Access-Control-Allow-Origin']
                    = $this->defaults['accessControlAllowOrigin'] == '*'
                    ? $origin
                    : $this->defaults['accessControlAllowOrigin'];
                $this->_responseHeaders['Access-Control-Allow-Credentials'] = 'true';
                $this->_responseHeaders['Access-Control-Max-Age'] = 86400;
            } elseif ($this->_requestMethod == 'OPTIONS') {
                $this->_responseHeaders['Access-Control-Allow-Origin']
                    = $this->defaults['accessControlAllowOrigin'];
                $this->_responseHeaders['Access-Control-Allow-Credentials'] = 'true';
            }
        }
        $this->_responseHeaders['Content-Language'] = $this->defaults['language'];

        if (isset($info->metadata['header'])) {
            foreach ($info->metadata['header'] as $header) {
                $parts = explode(': ', $header, 2);
                $this->_responseHeaders[$parts[0]] = $parts[1];
            }
        }

        $code = 200;
        if (!$this->defaults['suppressResponseCode']) {
            if ($e) {
                $code = $e->getCode();
            } elseif (!$this->_responseCode && isset($info->metadata['status'])) {
                $code = $info->metadata['status'];
            }
        }
        $this->_responseCode = $code;
        $this->responseFormat->charset($this->defaults['charset']);
        $charset = $this->responseFormat->charset()
            ?: $this->defaults['charset'];

        $this->_responseHeaders['Content-Type'] =
            $this->responseFormat->mediaType() . "; charset=$charset";
        if ($e && $e instanceof HttpException) {
            $this->_responseHeaders = $e->getHeaders() + $this->_responseHeaders;
        }
    }

    protected function message(Throwable $e, string $origin)
    {
        if (!$e instanceof HttpException) {
            $e = new HttpException($e->getCode(), $e->getMessage(), [], $e);
        }
        $this->_responseCode = $e->getCode();
        $this->composeHeaders(
            $this->_apiMethodInfo,
            $origin,
            $e
        );
        if ($e->emptyMessageBody) {
            return null;
        }
        /** @var ComposerInterface $compose */
        $compose = $this->make(ComposerInterface::class);
        return $compose->message($e);
    }

    abstract protected function respond($response = []): ResponseInterface;

    abstract protected function stream($data): ResponseInterface;

    abstract public function handle(ServerRequestInterface $request = null): PromiseInterface;

    private static function isPathSelected(string $class, string $path): bool
    {
        if (isset(class_implements($class)[SelectivePathsInterface::class])) {
            $notInPath = true;
            /** @var SelectivePathsInterface $class */
            foreach ($class::getIncludedPaths() as $include) {
                if (empty($include) || 0 === strpos($path, $include)) {
                    $notInPath = false;
                    break;
                }
            }
            if ($notInPath) {
                return false;
            }
            foreach ($class::getExcludedPaths() as $exclude) {
                if (empty($exclude) && empty($path)) {
                    return false;
                } elseif (0 === strpos($path, $exclude)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function __get($name)
    {
        return $this->{'_' . $name} ?? null;
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
            $value = Validator::validate($value, new ValidationInfo($detail));
        }
        $this->defaults[$property] = $value;
        return true;
    }
}