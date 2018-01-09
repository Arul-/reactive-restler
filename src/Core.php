<?php namespace Luracast\Restler;

use Illuminate\Contracts\Container\Container as ContainerContract;
use Luracast\Config\Config;
use Luracast\Restler\Contracts\AuthenticationInterface;
use Luracast\Restler\Contracts\FilterInterface;
use Luracast\Restler\Contracts\RequestMediaTypeInterface;
use Luracast\Restler\Contracts\ResponseMediaTypeInterface;
use Luracast\Restler\Contracts\UsesAuthenticationInterface;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\UrlEncoded;
use Luracast\Restler\MediaTypes\Xml;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

abstract class Core
{
    const VERSION = '4.0.0';

    protected $authenticated = false;
    protected $authVerified = false;
    /**
     * @var int
     */
    protected $requestedApiVersion = 1;

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
    protected $formatOverridesMap = ['extensions' => []];
    /**
     * @var RequestMediaTypeInterface
     */
    public $requestFormat;
    protected $body = [];
    protected $query = [];

    protected $responseHeaders = [];
    protected $responseCode = null;
    /**
     * @var ContainerContract
     */
    private $container;
    /**
     * @var Config
     */
    private $config;


    public function __construct(ContainerContract $container, Config $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    public function make($className)
    {
        return $this->init($this->container->make($className));
    }

    public function init($instance)
    {
        if ($m = $this->apiMethodInfo->metadata ?? false) {
            $fullName = get_class($instance);
            $shortName = Util::getShortName($fullName);
            $properties = Util::nestedValue(
                $m, 'class', $fullName,
                CommentParser::$embeddedDataName
            ) ?: (Util::nestedValue(
                $m, 'class', $shortName,
                CommentParser::$embeddedDataName
            ) ?: []);
            $objectVars = get_object_vars($instance);
            foreach ($properties as $property => $value) {
                if (property_exists($fullName, $property)) {
                    //if not a static property
                    array_key_exists($property, $objectVars)
                        ? $instance->{$property} = $value
                        : $instance::$$property = $value;
                }
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
        return str_replace(
            array_merge(
                Router::$formatMap['extensions'],
                $this->formatOverridesMap['extensions']
            ),
            '',
            trim($path, '/')
        );
    }

    protected function getQuery(array $get = []): array
    {
        $get = UrlEncoded::decoderTypeFix($get);
        //parse defaults
        //TODO: copy all properties of defaults and apply changes there
        foreach ($get as $key => $value) {
            if ($alias = $this->config['defaults.aliases.' . $key]) {
                unset($get[$key]);
                $get[$alias] = $value;
                $key = $alias;
            }
            if ($this->config['defaults.overridables.' . $key]) {
                $this->config['defaults.' . $key] = $value;
            }
        }
        return $get;
    }

    protected function getRequestMediaType(string $contentType): RequestMediaTypeInterface
    {
        $format = null;
        // check if client has sent any information on request format
        if (!empty($contentType)) {
            $mime = $contentType;
            if (false !== $pos = strpos($mime, ';')) {
                $mime = substr($mime, 0, $pos);
            }
            if ($mime == UrlEncoded::MIME) {
                $format = $this->make(UrlEncoded::class);
            } elseif (isset(Router::$formatMap[$mime])) {
                $format = $this->make(Router::$formatMap[$mime]);
                $format->mediaType($mime);
            } elseif (!$this->requestFormatDiffered && isset($this->formatOverridesMap[$mime])) {
                //if our api method is not using an @format comment
                //to point to this $mime, we need to throw 403 as in below
                //but since we don't know that yet, we need to defer that here
                $format = $this->make($this->formatOverridesMap[$mime]);
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
            $format = $this->make(Router::$formatMap['default']);
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
            //TODO: handle stream
            $r = $this->requestFormat->decode($raw);

            /*
            $r = $this->request->getParsedBody();

            if ($r == null) {
                $body = $this->request->getBody();
                $body->rewind();
                $content = $body->read($body->getSize());
                $r = json_decode($content);
            }
            */

            $r = is_array($r)
                ? array_merge($r, array($this->config['defaults.fullRequestDataName'] => $r))
                : array($this->config['defaults.fullRequestDataName'] => $r);
        }
        return $r;
    }

    /**
     * @throws HttpException
     */
    protected function route(): void
    {
        $this->apiMethodInfo = $o = Router::find(
            $this->path,
            $this->requestMethod,
            $this->requestedApiVersion,
            $this->body + $this->query
        );
        //set defaults based on api method comments
        if (isset($o->metadata)) {
            foreach ($this->config['defaults.fromComments'] as $key => $defaultsKey) {
                if (array_key_exists($key, $o->metadata)) {
                    $value = $o->metadata[$key];
                    $this->config['defaults.' . $defaultsKey] = $value;
                }
            }
        }
        if (!isset($o->className)) {
            throw new HttpException(404);
        }

        if (isset(Router::$versionMap[$o->className])) {
            Scope::$classAliases[Util::getShortName($o->className)]
                = Router::$versionMap[$o->className][$this->requestedApiVersion];
        }

        foreach (Router::$authClasses as $auth) {
            if (isset(Router::$versionMap[$auth])) {
                Scope::$classAliases[$auth] = Router::$versionMap[$auth][$this->requestedApiVersion];
            } elseif (isset(Router::$versionMap[Scope::$classAliases[$auth]])) {
                Scope::$classAliases[$auth]
                    = Router::$versionMap[Scope::$classAliases[$auth]][$this->requestedApiVersion];
            }
        }
    }

    abstract protected function negotiate(): void;

    /**
     * @param string $path
     * @param string $acceptHeader
     * @return ResponseMediaTypeInterface
     * @throws HttpException
     */
    protected function negotiateResponseMediaType(string $path, string $acceptHeader = ''): ResponseMediaTypeInterface
    {
        //check if the api method insists on response format using @format comment
        if (array_key_exists('format', $this->apiMethodInfo->metadata)) {
            $formats = explode(',', (string)$this->apiMethodInfo->metadata['format']);
            foreach ($formats as $i => $f) {
                $f = trim($f);
                if (!in_array($f, $this->formatOverridesMap)) {
                    throw new HttpException(
                        500,
                        "Given @format is not present in overriding formats. Please call `\$r->setOverridingFormats('$f');` first."
                    );
                }
                $formats[$i] = $f;
            }
            //TODO: fix this
            //call_user_func_array(array($this, 'setSupportedFormats'), $formats); //TODO: Fix this
        }

        // check if client has specified an extension
        /** @var $format ResponseMediaTypeInterface */
        $format = null;
        $extensions = explode('.', parse_url($path, PHP_URL_PATH));
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset(Router::$formatMap[$extension])) {
                $format = $this->make(Router::$formatMap[$extension]);
                $format->extension($extension);
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if (!empty($acceptHeader)) {
            $acceptList = Util::sortByPriority($acceptHeader);
            foreach ($acceptList as $accept => $quality) {
                if (isset(Router::$formatMap[$accept])) {
                    $format = $this->make(Router::$formatMap[$accept]);
                    $format->mediaType($accept);
                    // Tell cache content is based on Accept header
                    $this->responseHeaders['Vary'] = 'Accept';
                    return $format;

                } elseif (false !== ($index = strrpos($accept, '+'))) {
                    $mime = substr($accept, 0, $index);
                    if (is_string($this->config['defaults.apiVendor'])
                        && 0 === stripos($mime,
                            'application/vnd.'
                            . $this->config['defaults.apiVendor'] . '-v')
                    ) {
                        $extension = substr($accept, $index + 1);
                        if (isset(Router::$formatMap[$extension])) {
                            //check the MIME and extract version
                            $version = intval(substr($mime,
                                18 + strlen($this->config['defaults.apiVendor'])));
                            if ($version >= Router::$minimumVersion && $version <= Router::$maximumVersion) {
                                $this->requestedApiVersion = $version;
                                $format = $this->make(Router::$formatMap[$extension]);
                                $format->extension($extension);
                                $this->config['defaults.useVendorMIMEVersioning'] = true;
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
                $format = $this->make(Router::$formatMap['default']);
            }
        }
        if (empty($format)) {
            // RFC 2616: If an Accept header field is present, and if the
            // server cannot send a response which is acceptable according to
            // the combined Accept field value, then the server SHOULD send
            // a 406 (not acceptable) response.
            $format = $this->make(Router::$formatMap['default']);
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
        if ($this->config['defaults.crossOriginResourceSharing'] || $requestMethod != 'OPTIONS') {
            return;
        }
        if (!empty($accessControlRequestMethod)) {
            $this->responseHeaders['Access-Control-Allow-Methods'] = $this->config['defaults.accessControlAllowMethods'];
        }
        if (!empty($accessControlRequestHeaders)) {
            $this->responseHeaders['Access-Control-Allow-Headers'] = $accessControlRequestHeaders;
        }
        $this->responseHeaders['Access-Control-Allow-Origin'] =
            $this->config['defaults.accessControlAllowOrigin'] == '*' && !empty($origin)
                ? $origin : $this->config['defaults.accessControlAllowOrigin'];
        $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
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
            $charList = Util::sortByPriority($acceptCharset);
            foreach ($charList as $charset => $quality) {
                if (in_array($charset, $this->config['defaults.supportedCharsets'])) {
                    $found = true;
                    $this->config['defaults.charset'] = $charset;
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
            $langList = Util::sortByPriority($acceptLanguage);
            foreach ($langList as $lang => $quality) {
                foreach ($this->config['defaults.supportedLanguages'] as $supported) {
                    if (strcasecmp($supported, $lang) == 0) {
                        $found = true;
                        $this->config['defaults.language'] = $supported;
                        break 2;
                    }
                }
            }
            if (!$found) {
                if (strpos($acceptLanguage, '*') !== false) {
                    //use default language
                } else {
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
        $filterClasses = $postAuth ? Router::$postAuthFilterClasses : Router::$preAuthFilterClasses;
        foreach ($filterClasses as $filerClass) {
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
     */
    protected function authenticate(ServerRequestInterface $request)
    {
        $o = &$this->apiMethodInfo;
        $accessLevel = max($this->config['defaults.apiAccessLevel'], $o->accessLevel);
        if ($accessLevel) {
            if (!count(Router::$authClasses) && $accessLevel > 1) {
                throw new HttpException(
                    403,
                    'at least one Authentication Class is required'
                );
            }
            $unauthorized = false;
            foreach (Router::$authClasses as $authClass) {
                try {
                    /**
                     * @var AuthenticationInterface
                     */
                    $authObj = $this->make($authClass);
                    if (!$authObj->__isAllowed($request, $this->responseHeaders)) {
                        throw new HttpException(401);
                    }
                    $unauthorized = false;
                    break;
                } catch (InvalidAuthCredentials $e) {
                    $this->authenticated = false;
                    throw $e;
                } catch (HttpException $e) {
                    if (!$unauthorized) {
                        $unauthorized = $e;
                    }
                }
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

    protected function validate()
    {
        if (!$this->config['defaults.autoValidationEnabled']) {
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
                $validator = $this->config['defaults.validatorClass'];
                $this->make($validator);
                //if(!is_subclass_of($validator, 'Luracast\\Restler\\Data\\iValidate')) {
                //changed the above test to below for addressing this php bug
                //https://bugs.php.net/bug.php?id=53727
                if (function_exists("$validator::validate")) {
                    throw new \UnexpectedValueException(
                        '`Defaults::$validatorClass` must implement `iValidate` interface'
                    );
                }
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

    protected function call()
    {
        $o = &$this->apiMethodInfo;
        $accessLevel = max($this->config['defaults.apiAccessLevel'], $o->accessLevel);
        $object = $this->make($o->className);
        switch ($accessLevel) {
            case 3 : //protected method
                $reflectionMethod = new \ReflectionMethod(
                    $object,
                    $o->methodName
                );
                $reflectionMethod->setAccessible(true);
                $result = $reflectionMethod->invokeArgs(
                    $object,
                    $o->parameters
                );
                break;
            default :
                $result = call_user_func_array(array(
                    $object,
                    $o->methodName
                ), $o->parameters);
        }
        return $result;
    }

    abstract protected function compose($response = null);


    protected function composeHeaders(?ApiMethodInfo $info, string $origin = '', RestException $e = null): void
    {
        //only GET method should be cached if allowed by API developer
        $expires = $this->requestMethod == 'GET' ? $this->config['defaults.headerExpires'] : 0;
        if (!is_array($this->config['defaults.headerCacheControl'])) {
            $this->config['defaults.headerCacheControl'] = [$this->config['defaults.headerCacheControl']];
        }
        $cacheControl = $this->config['defaults.headerCacheControl.0'];
        if ($expires > 0) {
            $cacheControl = !isset($this->apiMethodInfo->accessLevel) || $this->apiMethodInfo->accessLevel
                ? 'private, ' : 'public, ';
            $cacheControl .= end($this->config['defaults.headerCacheControl']);
            $cacheControl = str_replace('{expires}', $expires, $cacheControl);
            $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $expires);
        }
        $this->responseHeaders['Cache-Control'] = $cacheControl;
        $this->responseHeaders['Expires'] = $expires;
        $this->responseHeaders['X-Powered-By'] = 'Luracast Restler v' . static::VERSION;

        if ($this->config['defaults.crossOriginResourceSharing']
            && !empty($origin)
        ) {
            $this->responseHeaders['Access-Control-Allow-Origin']
                = $this->config['defaults.accessControlAllowOrigin'] == '*'
                ? $origin
                : $this->config['defaults.accessControlAllowOrigin'];
            $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
            $this->responseHeaders['Access-Control-Max-Age'] = 86400;
        }

        $this->responseHeaders['Content-Language'] = $this->config['defaults.language'];

        if (isset($info->metadata['header'])) {
            foreach ($info->metadata['header'] as $header) {
                $parts = explode(': ', $header, 2);
                $this->responseHeaders[$parts[0]] = $parts[1];
            }
        }

        $code = 200;
        if (!$this->config['defaults.suppressResponseCode']) {
            if ($e) {
                $code = $e->getCode();
            } elseif (!$this->responseCode && isset($info->metadata['status'])) {
                $code = $info->metadata['status'];
            }
        }
        $this->responseCode = $code;
        $this->responseFormat->charset($this->config['defaults.charset']);
        $charset = $this->responseFormat->charset()
            ?: $this->config['defaults.charset'];

        $this->responseHeaders['Content-Type'] = (
            $this->config['defaults.useVendorMIMEVersioning']
                ? 'application/vnd.' . $this->config['defaults.apiVendor']
                . "-v{$this->requestedApiVersion}+"
                . $this->responseFormat->extension()
                : $this->responseFormat->mediaType())
            . "; charset=$charset";
        if ($e && $e instanceof HttpException) {
            $this->responseHeaders = $e->getHeaders() + $this->responseHeaders;
        }
    }

    protected function message(Throwable $e, string $origin)
    {
        if (!$e instanceof RestException) {
            $e = new HttpException(500, $e->getMessage(), [], $e);
        } elseif (!$e instanceof HttpException) {
            $e = new HttpException($e->getCode(), $e->getMessage(), [], $e);
        }
        $this->responseCode = $e->getCode();
        if ($e->emptyMessageBody) {
            return null;
        }
        $this->composeHeaders(
            $this->apiMethodInfo,
            $this->request->getHeaderLine('origin'),
            $e
        );
        /**
         * @var iCompose Default Composer
         */
        $compose = $this->make($this->config['defaults.composeClass']);
        return $compose->message($e);
    }

    abstract protected function respond($response = []): ResponseInterface;


    abstract protected function stream($data): ResponseInterface;


    abstract public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $rawRequestBody = ''
    ): ResponseInterface;
}