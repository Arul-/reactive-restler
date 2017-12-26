<?php declare(strict_types=1);

use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\iFormat;
use Luracast\Restler\Format\UrlEncodedFormat;
use Luracast\Restler\InvalidAuthCredentials;
use Luracast\Restler\RestException;
use Luracast\Restler\Scope;
use Luracast\Restler\Util;

abstract class Core
{
    const VERSION = '4.0.0';

    protected $apiVersionMap = [];
    /**
     * @var int
     */
    protected $apiVersion = 1;
    /**
     * @var int
     */
    protected $requestedApiVersion = 1;
    /**
     * @var int
     */
    protected $apiMinimumVersion = 1;

    protected $requestMethod = 'GET';
    /**
     * @var bool
     */
    protected $requestFormatDiffered = false;
    /**
     * @var array
     */
    protected $readableMimeTypes = [];
    /**
     * @var ApiMethodInfo
     */
    protected $apiMethodInfo;
    /**
     * @var iFormat
     */
    public $responseFormat;
    /**
     * @var array
     */
    protected $writableMimeTypes = [];
    protected $path = '';
    protected $formatOverridesMap = ['extensions' => []];
    /**
     * @var iFormat
     */
    public $requestFormat;
    protected $body = [];
    protected $formatMap = [];
    protected $query = [];
    protected $authClasses = [];

    protected $responseHeaders = [];
    protected $responseCode = 200;

    /**
     * protected methods will need at least one authentication class to be set
     * in order to allow that method to be executed
     *
     * @param string $className of the authentication class
     * @param string $resourcePath optional url prefix for mapping
     * @throws Exception
     * @throws HttpException
     */
    public function addAuthenticationClass($className, $resourcePath = null)
    {
        $this->authClasses[] = $className;
        $this->addAPIClass($className, $resourcePath);
    }

    /**
     * @param $className
     * @param null|string $resourcePath
     * @throws Exception
     * @throws HttpException
     */
    public function addAPIClass($className, $resourcePath = null)
    {
        try {
            if (isset(Scope::$classAliases[$className])) {
                $className = Scope::$classAliases[$className];
            }
            $maxVersionMethod = '__getMaximumSupportedVersion';
            if (class_exists($className)) {
                if (method_exists($className, $maxVersionMethod)) {
                    $max = $className::$maxVersionMethod();
                    for ($i = 1; $i <= $max; $i++) {
                        $this->apiVersionMap[$className][$i] = $className;
                    }
                } else {
                    $this->apiVersionMap[$className][1] = $className;
                }
            }
            //versioned api
            if (false !== ($index = strrpos($className, '\\'))) {
                $name = substr($className, 0, $index)
                    . '\\v{$version}' . substr($className, $index);
            } else {
                if (false !== ($index = strrpos($className, '_'))) {
                    $name = substr($className, 0, $index)
                        . '_v{$version}' . substr($className, $index);
                } else {
                    $name = 'v{$version}\\' . $className;
                }
            }

            for ($version = $this->apiMinimumVersion;
                 $version <= $this->apiVersion;
                 $version++) {

                $versionedClassName = str_replace('{$version}', $version,
                    $name);
                if (class_exists($versionedClassName)) {
                    Router::addAPIClass($versionedClassName,
                        Util::getResourcePath(
                            $className,
                            $resourcePath
                        ),
                        $version
                    );
                    if (method_exists($versionedClassName, $maxVersionMethod)) {
                        $max = $versionedClassName::$maxVersionMethod();
                        for ($i = $version; $i <= $max; $i++) {
                            $this->apiVersionMap[$className][$i] = $versionedClassName;
                        }
                    } else {
                        $this->apiVersionMap[$className][$version] = $versionedClassName;
                    }
                } elseif (isset($this->apiVersionMap[$className][$version])) {
                    Router::addAPIClass($this->apiVersionMap[$className][$version],
                        Util::getResourcePath(
                            $className,
                            $resourcePath
                        ),
                        $version
                    );
                }
            }
        } catch (Exception $e) {
            $e = new Exception(
                "addAPIClass('$className') failed. " . $e->getMessage(),
                $e->getCode(),
                $e
            );
            $this->setSupportedFormats('JsonFormat');
            $this->message($e);
        }
    }

    /**
     * @param int $version maximum version number supported
     *                                     by  the api
     * @param int $minimum minimum version number supported
     * (optional)
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public function setAPIVersion(int $version = 1, int $minimum = 1): void
    {
        $this->apiVersion = $version;
        if (is_int($minimum)) {
            $this->apiMinimumVersion = $minimum;
        }
    }

    /**
     * @param string[] ...$formats
     *
     * @throws Exception
     * @throws HttpException
     */
    public function setSupportedFormats(string ...$formats)
    {
        $extensions = [];
        $throwException = $this->requestFormatDiffered;
        $this->writableMimeTypes = $this->readableMimeTypes = [];
        foreach ($formats as $className) {

            $obj = Scope::get($className);

            if (!$obj instanceof iFormat) {
                throw new Exception('Invalid format class; must implement ' .
                    'iFormat interface');
            }
            if ($throwException && get_class($obj) == get_class($this->requestFormat)) {
                $throwException = false;
            }

            foreach ($obj->getMIMEMap() as $mime => $extension) {
                if ($obj->isWritable()) {
                    $this->writableMimeTypes[] = $mime;
                    $extensions[".$extension"] = true;
                }
                if ($obj->isReadable()) {
                    $this->readableMimeTypes[] = $mime;
                }
                if (!isset($this->formatMap[$extension])) {
                    $this->formatMap[$extension] = $className;
                }
                if (!isset($this->formatMap[$mime])) {
                    $this->formatMap[$mime] = $className;
                }
            }
        }
        if ($throwException) {
            throw new HttpException(
                403,
                'Content type `' . $this->requestFormat->getMIME() . '` is not supported.'
            );
        }
        $this->formatMap['default'] = $formats[0];
        $this->formatMap['extensions'] = array_keys($extensions);
    }

    /**
     * Sets the cleaned up path without extensions and unwanted slashes
     * @param string $path
     * @return string
     */
    protected function getPath(string $path): string
    {
        return str_replace(
            array_merge(
                $this->formatMap['extensions'],
                $this->formatOverridesMap['extensions']
            ),
            '',
            trim($path, '/')
        );
    }

    protected function getQuery(array $get = []): array
    {
        $get = UrlEncodedFormat::decoderTypeFix($get);
        //parse defaults
        foreach ($get as $key => $value) {
            if (isset(Defaults::$aliases[$key])) {
                unset($get[$key]);
                $get[Defaults::$aliases[$key]] = $value;
                $key = Defaults::$aliases[$key];
            }
            if (in_array($key, Defaults::$overridables)) {
                Defaults::setProperty($key, $value);
            }
        }
        return $get;
    }

    /**
     * Parses the request to figure out format of the request data
     *
     * @throws HttpException
     * @return iFormat any class that implements iFormat
     * @example JsonFormat
     */
    protected function getRequestFormat(string $contentType): iFormat
    {
        $format = null;
        // check if client has sent any information on request format
        if (!empty($contentType)) {
            $mime = $contentType;
            if (false !== $pos = strpos($mime, ';')) {
                $mime = substr($mime, 0, $pos);
            }
            if ($mime == UrlEncodedFormat::MIME) {
                $format = Scope::get('UrlEncodedFormat');
            } elseif (isset($this->formatMap[$mime])) {
                $format = Scope::get($this->formatMap[$mime]);
                $format->setMIME($mime);
            } elseif (!$this->requestFormatDiffered && isset($this->formatOverridesMap[$mime])) {
                //if our api method is not using an @format comment
                //to point to this $mime, we need to throw 403 as in below
                //but since we don't know that yet, we need to defer that here
                $format = Scope::get($this->formatOverridesMap[$mime]);
                $format->setMIME($mime);
                $this->requestFormatDiffered = true;
            } else {
                throw new HttpException(
                    403,
                    "Content type `$mime` is not supported."
                );
            }
        }
        if (!$format) {
            $format = Scope::get($this->formatMap['default']);
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
                ? array_merge($r, array(Defaults::$fullRequestDataName => $r))
                : array(Defaults::$fullRequestDataName => $r);
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
            foreach (Defaults::$fromComments as $key => $defaultsKey) {
                if (array_key_exists($key, $o->metadata)) {
                    $value = $o->metadata[$key];
                    Defaults::setProperty($defaultsKey, $value);
                }
            }
        }
        if (!isset($o->className)) {
            throw new HttpException(404);
        }

        if (isset($this->apiVersionMap[$o->className])) {
            Scope::$classAliases[Util::getShortName($o->className)]
                = $this->apiVersionMap[$o->className][$this->requestedApiVersion];
        }

        foreach ($this->authClasses as $auth) {
            if (isset($this->apiVersionMap[$auth])) {
                Scope::$classAliases[$auth] = $this->apiVersionMap[$auth][$this->requestedApiVersion];
            } elseif (isset($this->apiVersionMap[Scope::$classAliases[$auth]])) {
                Scope::$classAliases[$auth]
                    = $this->apiVersionMap[Scope::$classAliases[$auth]][$this->requestedApiVersion];
            }
        }
    }

    /**
     * @param string $path
     * @param string $acceptHeader
     * @return iFormat
     * @throws HttpException
     */
    protected function negotiateResponseFormat(string $path, string $acceptHeader = ''): iFormat
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
            call_user_func_array(array($this, 'setSupportedFormats'), $formats);
        }

        // check if client has specified an extension
        /** @var $format iFormat */
        $format = null;
        $extensions = explode('.', parse_url($path, PHP_URL_PATH));
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset($this->formatMap[$extension])) {
                $format = Scope::get($this->formatMap[$extension]);
                $format->setExtension($extension);
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if (!empty($acceptHeader)) {
            $acceptList = Util::sortByPriority($acceptHeader);
            foreach ($acceptList as $accept => $quality) {
                if (isset($this->formatMap[$accept])) {
                    $format = Scope::get($this->formatMap[$accept]);
                    $format->setMIME($accept);
                    // Tell cache content is based on Accept header
                    $this->responseHeaders['Vary'] = 'Accept';
                    return $format;

                } elseif (false !== ($index = strrpos($accept, '+'))) {
                    $mime = substr($accept, 0, $index);
                    if (is_string(Defaults::$apiVendor)
                        && 0 === stripos($mime,
                            'application/vnd.'
                            . Defaults::$apiVendor . '-v')
                    ) {
                        $extension = substr($accept, $index + 1);
                        if (isset($this->formatMap[$extension])) {
                            //check the MIME and extract version
                            $version = intval(substr($mime,
                                18 + strlen(Defaults::$apiVendor)));
                            if ($version > 0 && $version <= $this->apiVersion) {
                                $this->requestedApiVersion = $version;
                                $format = Scope::get($this->formatMap[$extension]);
                                $format->setExtension($extension);
                                Defaults::$useVendorMIMEVersioning = true;
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
                $format = Scope::get('JsonFormat');
            } elseif (false !== strpos($acceptHeader, 'text/*')) {
                $format = Scope::get('XmlFormat');
            } elseif (false !== strpos($acceptHeader, '*/*')) {
                $format = Scope::get($this->formatMap['default']);
            }
        }
        if (empty($format)) {
            // RFC 2616: If an Accept header field is present, and if the
            // server cannot send a response which is acceptable according to
            // the combined Accept field value, then the server SHOULD send
            // a 406 (not acceptable) response.
            $format = Scope::get($this->formatMap['default']);
            $this->responseFormat = $format;
            throw new HttpException(
                406,
                'Content negotiation failed. ' .
                'Try `' . $format->getMIME() . '` instead.'
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
        if (Defaults::$crossOriginResourceSharing || $requestMethod != 'OPTIONS') {
            return;
        }
        if (!empty($accessControlRequestMethod)) {
            $this->responseHeaders['Access-Control-Allow-Methods'] = Defaults::$accessControlAllowMethods;
        }
        if (!empty($accessControlRequestHeaders)) {
            $this->responseHeaders['Access-Control-Allow-Headers'] = $accessControlRequestHeaders;
        }
        $this->responseHeaders['Access-Control-Allow-Origin'] =
            Defaults::$accessControlAllowOrigin == '*' && !empty($origin)
                ? $origin : Defaults::$accessControlAllowOrigin;
        $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
        throw new HttpException(200, '');
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
                if (in_array($charset, Defaults::$supportedCharsets)) {
                    $found = true;
                    Defaults::$charset = $charset;
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
                foreach (Defaults::$supportedLanguages as $supported) {
                    if (strcasecmp($supported, $lang) == 0) {
                        $found = true;
                        Defaults::$language = $supported;
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
     * @throws InvalidAuthCredentials
     * @throws HttpException
     */
    protected function authenticate()
    {
        $o = &$this->apiMethodInfo;
        $accessLevel = max(Defaults::$apiAccessLevel, $o->accessLevel);
        if ($accessLevel) {
            if (!count($this->authClasses) && $accessLevel > 1) {
                throw new HttpException(
                    403,
                    'at least one Authentication Class is required'
                );
            }
            $unauthorized = false;
            foreach ($this->authClasses as $authClass) {
                try {
                    $authObj = Scope::get($authClass);
                    if (!method_exists($authObj, Defaults::$authenticationMethod)) {
                        throw new HttpException (
                            500, 'Authentication Class ' .
                            'should implement iAuthenticate');
                    } elseif (
                    !$authObj->{Defaults::$authenticationMethod}()
                    ) {
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

    abstract protected function compose($response = null);

    public function composeHeaders(
        ?ApiMethodInfo $info,
        string $origin = '',
        RestException $e = null
    ): void {
        //only GET method should be cached if allowed by API developer
        $expires = $this->requestMethod == 'GET' ? Defaults::$headerExpires : 0;
        if (!is_array(Defaults::$headerCacheControl)) {
            Defaults::$headerCacheControl = [Defaults::$headerCacheControl];
        }
        $cacheControl = Defaults::$headerCacheControl[0];
        if ($expires > 0) {
            $cacheControl = $this->apiMethodInfo->accessLevel
                ? 'private, ' : 'public, ';
            $cacheControl .= end(Defaults::$headerCacheControl);
            $cacheControl = str_replace('{expires}', $expires, $cacheControl);
            $expires = gmdate('D, d M Y H:i:s \G\M\T', time() + $expires);
        }
        $this->responseHeaders['Cache-Control'] = $cacheControl;
        $this->responseHeaders['Expires'] = $expires;
        $this->responseHeaders['X-Powered-By'] = 'Luracast Restler v' . static::VERSION;

        if (Defaults::$crossOriginResourceSharing
            && !empty($origin)
        ) {
            $this->responseHeaders['Access-Control-Allow-Origin'] = Defaults::$accessControlAllowOrigin == '*'
                ? $origin
                : Defaults::$accessControlAllowOrigin;
            $this->responseHeaders['Access-Control-Allow-Credentials'] = 'true';
            $this->responseHeaders['Access-Control-Max-Age'] = 86400;
        }

        $this->responseHeaders['Content-Language'] = Defaults::$language;

        if (isset($info->metadata['header'])) {
            foreach ($info->metadata['header'] as $header) {
                $parts = explode(': ', $header, 2);
                $this->responseHeaders[$parts[0]] = $parts[1];
            }
        }

        $code = 200;
        if (!Defaults::$suppressResponseCode) {
            if ($e) {
                $code = $e->getCode();
            } elseif (!$this->responseCode && isset($info->metadata['status'])) {
                $code = $info->metadata['status'];
            }
        }
        $this->responseCode = $code;
        $this->responseFormat->setCharset(Defaults::$charset);
        $charset = $this->responseFormat->getCharset()
            ?: Defaults::$charset;

        $this->responseHeaders['Content-Type'] = (
            Defaults::$useVendorMIMEVersioning
                ? 'application/vnd.' . Defaults::$apiVendor
                . "-v{$this->requestedApiVersion}+"
                . $this->responseFormat->getExtension()
                : $this->responseFormat->getMIME())
            . "; charset=$charset";
        if ($e && $e instanceof HttpException) {
            $this->responseHeaders = $e->getHeaders() + $this->responseHeaders;
        }
    }

    abstract protected function message(Throwable $e);

    abstract protected function respond($response = []);
}