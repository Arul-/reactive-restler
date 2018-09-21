<?php namespace Luracast\Restler;

use ArrayAccess;
use Exception;
use Luracast\Restler\Contracts\{
    AuthenticationInterface, ComposerInterface, ContainerInterface, FilterInterface, RequestMediaTypeInterface,
    ResponseMediaTypeInterface, SelectivePathsInterface, UsesAuthenticationInterface, ValidationInterface
};
use Luracast\Restler\Exceptions\{
    HttpException, InvalidAuthCredentials
};
use Luracast\Restler\MediaTypes\{
    Json, UrlEncoded, Xml
};
use Luracast\Restler\Utils\{
    ApiMethodInfo, ClassName, CommentParser, Header, Validator, ValidationInfo
};
use Psr\Http\Message\{
    ResponseInterface, ServerRequestInterface
};
use ReflectionException;
use ReflectionMethod;
use Throwable;
use TypeError;

abstract class Core
{
    const VERSION = '4.0.0';

    protected $authenticated = false;
    protected $authVerified = false;
    /**
     * @var int
     */
    public $requestedApiVersion = 1;

    protected $requestMethod = 'GET';
    /**
     * @var bool
     */
    protected $requestFormatDiffered = false;
    /**
     * @var ApiMethodInfo
     */
    protected $apiMethodInfo;
    /**
     * @var ResponseMediaTypeInterface
     */
    public $responseFormat;
    protected $path = '';
    /**
     * @var RequestMediaTypeInterface
     */
    public $requestFormat;
    protected $body = [];
    protected $query = [];

    protected $responseHeaders = [];
    protected $responseCode = null;
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var iterable
     */
    protected $config;
    /**
     * @var iterable
     */
    protected $app;
    /**
     * @var iterable
     */
    protected $router;
    /**
     * @var int for calculating execution time
     */
    protected $startTime;

    /**
     * Core constructor.
     * @param ContainerInterface $container
     * @param array $config
     * @throws TypeError
     */
    public function __construct(ContainerInterface $container = null, &$config = [])
    {
        if (!is_array($config) && !$config instanceof ArrayAccess) {
            throw new TypeError('Argument 2 passed to ' . __CLASS__
                . '::__construct() must be an array or implement ArrayAccess');
        }

        $this->startTime = time();

        $config = $config ?? [];
        $this->config = &$config;

        $this->config['defaults'] = $this->app = get_class_vars(Defaults::class);
        $this->config['router'] = $this->router = get_class_vars(Router::class);

        if ($container) {
            $container->init($config);
        } else {
            $container = new Container($config);
        }
        $this->container = $container;
        $this->container->instance(Core::class, $this);
    }


    /**
     * reset all properties to initial stage
     */
    protected function reset()
    {

        $this->authenticated = false;
        $this->authVerified = false;
        $this->requestedApiVersion = 1;
        $this->requestMethod = 'GET';
        $this->requestFormatDiffered = false;
        $this->apiMethodInfo = null;
        $this->responseFormat = null;
        $this->path = '';
        $this->requestFormat = null;
        $this->body = [];
        $this->query = [];
        $this->responseHeaders = [];
        $this->responseCode = null;
        $this->startTime = time();

        $this->config['defaults'] = $this->app = get_class_vars(Defaults::class);
        $this->config['router'] = $this->router = get_class_vars(Router::class);

        $this->container->init($this->config);
        $this->container->instance(Core::class, $this);
    }

    public function make($className)
    {
        $properties = [];
        $fullName = $className;
        if ($m = $this->apiMethodInfo->metadata ?? false) {
            $shortName = ClassName::short($fullName);
            $properties = $m['class'][$fullName][CommentParser::$embeddedDataName] ??
                $m['class'][$shortName][CommentParser::$embeddedDataName] ?? [];
            $name = lcfirst($shortName);
            if (!isset($this->config[$name])) {
                $this->config[$name] = get_class_vars($fullName);
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
            $instance->__setAuthenticationStatus($this->authenticated, $this->authVerified);
        }

        return $instance;
    }

    abstract protected function get(): void;

    protected function getPath(string $path): string
    {
        $path = str_replace(
            array_merge(
                $this->router['responseFormatMap']['extensions'],
                $this->router['formatOverridesMap']['extensions']
            ),
            '',
            trim($path, '/')
        );
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
            if ($alias = $this->app['fromQuery'][$key] ?? false) {
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
        if ($this->requestMethod == 'PUT'
            || $this->requestMethod == 'PATCH'
            || $this->requestMethod == 'POST'
        ) {
            $r = $this->requestFormat->decode($raw);

            $r = is_array($r)
                ? array_merge($r, array($this->app['fullRequestDataName'] => $r))
                : array($this->app['fullRequestDataName'] => $r);
        }
        return $r;
    }

    /**
     * @throws HttpException
     * @throws Exception
     */
    protected function route(): void
    {
        $this->apiMethodInfo = $o = Router::find(
            $this->path,
            $this->requestMethod,
            $this->requestedApiVersion,
            $this->body + $this->query
        );
        $this->container->instance(ApiMethodInfo::class, $o);
        //set defaults based on api method comments
        if (isset($o->metadata)) {
            foreach ($this->app['fromComments'] as $key => $property) {
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
        //check if the api method insists on response format using @format comment
        if (($metadata = $this->apiMethodInfo->metadata ?? false) && ($formats = $metadata['format'] ?? false)) {
            $formats = explode(',', (string)$formats);
            foreach ($formats as $i => $f) {
                if ($f = ClassName::resolve(trim($f), $metadata['scope'])) {
                    if (!in_array($f, $this->router['formatOverridesMap'])) {
                        throw new HttpException(
                            500,
                            "Given @format is not present in overriding formats. " .
                            "Please call `Router::\$setOverridingResponseMediaTypes('$f');` first."
                        );
                    }
                    $formats[$i] = $f;
                }
            }
            /** @noinspection PhpInternalEntityUsedInspection */
            Router::_setMediaTypes(RequestMediaTypeInterface::class, $formats,
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
                    $this->responseHeaders['Vary'] = 'Accept';
                    return $format;

                } elseif (false !== ($index = strrpos($accept, '+'))) {
                    $mime = substr($accept, 0, $index);
                    $vendor = 'application/vnd.'
                        . $this->app['apiVendor'] . '-v';
                    if (is_string($this->app['apiVendor']) && 0 === stripos($mime, $vendor)) {
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
                                $this->responseHeaders['Vary'] = 'Accept';
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
            $this->responseHeaders['Vary'] = 'Accept';
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
        if (!$this->app['crossOriginResourceSharing'] || $requestMethod != 'OPTIONS') {
            return;
        }
        if (!empty($accessControlRequestMethod)) {
            $this->responseHeaders['Access-Control-Allow-Methods'] = $this->app['accessControlAllowMethods'];
        }
        if (!empty($accessControlRequestHeaders)) {
            $this->responseHeaders['Access-Control-Allow-Headers'] = $accessControlRequestHeaders;
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
                if (in_array($charset, $this->app['supportedCharsets'])) {
                    $found = true;
                    $this->app['charset'] = $charset;
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
                foreach ($this->app['supportedLanguages'] as $supported) {
                    if (strcasecmp($supported, $lang) == 0) {
                        $found = true;
                        $this->app['language'] = $supported;
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
            if (!static::isPathSelected($filerClass, $this->path)) {
                array_splice($this->router[$name], $i, 1);
                continue;
            }
            /** @var FilterInterface $filter */
            $filter = $this->make($filerClass);
            if (!$filter->__isAllowed($request, $this->responseHeaders)) {
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
        $o = &$this->apiMethodInfo;
        $accessLevel = max($this->app['apiAccessLevel'], $o->accessLevel);
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
                    if (!static::isPathSelected($authClass, $this->path)) {
                        array_splice($this->router['authClasses'], $i, 1);
                        continue;
                    }
                    /** @var AuthenticationInterface $auth */
                    $auth = $this->make($authClass);
                    if (!$auth->__isAllowed($request, $this->responseHeaders)) {
                        throw new HttpException(401);
                    }
                    $unauthorized = false;
                    //make this auth class as the first one
                    array_splice($this->router['authClasses'], $i, 1);
                    array_unshift($this->router['authClasses'], $authClass);
                    break;
                } catch (InvalidAuthCredentials $e) { //provided credentials does not authenticate
                    $this->authenticated = false;
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
                    'at least one Authentication Class should apply to path `' . $this->path . '`'
                );
            }
            $this->authVerified = true;
            if ($unauthorized) {
                if ($accessLevel > 1) { //when it is not a hybrid api
                    throw $unauthorized;
                } else {
                    $this->authenticated = false;
                }
            } else {
                $this->authenticated = true;
            }
        }
    }

    /**
     *
     */
    protected function validate()
    {
        if (!$this->app['autoValidationEnabled']) {
            return;
        }

        $o = &$this->apiMethodInfo;
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
        $accessLevel = max($this->app['apiAccessLevel'], $info->accessLevel);
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
        $expires = $this->requestMethod == 'GET' ? $this->app['headerExpires'] : 0;
        if (!is_array($this->app['headerCacheControl'])) {
            $this->app['headerCacheControl'] = [$this->app['headerCacheControl']];
        }
        $cacheControl = $this->app['headerCacheControl'][0];
        if ($expires > 0) {
            $cacheControl = !isset($this->apiMethodInfo->accessLevel) || $this->apiMethodInfo->accessLevel
                ? 'private, ' : 'public, ';
            $cacheControl .= end($this->app['headerCacheControl']);
            $cacheControl = str_replace('{expires}', $expires, $cacheControl);
            $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $expires);
        }
        $this->responseHeaders['Date'] = gmdate('D, d M Y H:i:s \G\M\T', $this->startTime);
        $this->responseHeaders['Cache-Control'] = $cacheControl;
        $this->responseHeaders['Expires'] = $expires;
        $this->responseHeaders['X-Powered-By'] = 'Luracast Restler v' . static::VERSION;

        if ($this->app['crossOriginResourceSharing']) {
            if (!empty($origin)) {
                $this->responseHeaders['Access-Control-Allow-Origin']
                    = $this->app['accessControlAllowOrigin'] == '*'
                    ? $origin
                    : $this->app['accessControlAllowOrigin'];
                $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
                $this->responseHeaders['Access-Control-Max-Age'] = 86400;
            } elseif ($this->requestMethod == 'OPTIONS') {
                $this->responseHeaders['Access-Control-Allow-Origin']
                    = $this->app['accessControlAllowOrigin'];
                $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
            }
        }
        $this->responseHeaders['Content-Language'] = $this->app['language'];

        if (isset($info->metadata['header'])) {
            foreach ($info->metadata['header'] as $header) {
                $parts = explode(': ', $header, 2);
                $this->responseHeaders[$parts[0]] = $parts[1];
            }
        }

        $code = 200;
        if (!$this->app['suppressResponseCode']) {
            if ($e) {
                $code = $e->getCode();
            } elseif (!$this->responseCode && isset($info->metadata['status'])) {
                $code = $info->metadata['status'];
            }
        }
        $this->responseCode = $code;
        $this->responseFormat->charset($this->app['charset']);
        $charset = $this->responseFormat->charset()
            ?: $this->app['charset'];

        $this->responseHeaders['Content-Type'] =
            $this->responseFormat->mediaType() . "; charset=$charset";
        if ($e && $e instanceof HttpException) {
            $this->responseHeaders = $e->getHeaders() + $this->responseHeaders;
        }
    }

    protected function message(Throwable $e, string $origin)
    {
        if (!$e instanceof HttpException) {
            $e = new HttpException($e->getCode(), $e->getMessage(), [], $e);
        }
        $this->responseCode = $e->getCode();
        $this->composeHeaders(
            $this->apiMethodInfo,
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


    abstract public function handle(ServerRequestInterface $request): ResponseInterface;

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
        $this->app[$property] = $value;
        return true;
    }
}