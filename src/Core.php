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
     * @var iFormat
     */
    public $responseFormat;
    protected $path = '';
    protected $formatOverridesMap = ['extensions' => []];
    /**
     * @var iFormat
     */
    public $requestFormat;
    protected $body = [];
    protected $query = [];

    protected $responseHeaders = [];
    protected $responseCode = 200;

    public function modifyResponse(array $headers, $responseCode = 200)
    {
        $this->responseHeaders = $headers;
        $this->responseCode = $responseCode;
    }

    abstract public function requestHeader(string $name): string;

    /**
     * Sets the cleaned up path without extensions and unwanted slashes
     * @param string $path
     * @return string
     */
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
            } elseif (isset(Router::$formatMap[$mime])) {
                $format = Scope::get(Router::$formatMap[$mime]);
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
            $format = Scope::get(Router::$formatMap['default']);
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
            call_user_func_array(array($this, 'setSupportedFormats'), $formats); //TODO: Fix this
        }

        // check if client has specified an extension
        /** @var $format iFormat */
        $format = null;
        $extensions = explode('.', parse_url($path, PHP_URL_PATH));
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset(Router::$formatMap[$extension])) {
                $format = Scope::get(Router::$formatMap[$extension]);
                $format->setExtension($extension);
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if (!empty($acceptHeader)) {
            $acceptList = Util::sortByPriority($acceptHeader);
            foreach ($acceptList as $accept => $quality) {
                if (isset(Router::$formatMap[$accept])) {
                    $format = Scope::get(Router::$formatMap[$accept]);
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
                        if (isset(Router::$formatMap[$extension])) {
                            //check the MIME and extract version
                            $version = intval(substr($mime,
                                18 + strlen(Defaults::$apiVendor)));
                            if ($version >= Router::$minimumVersion && $version <= Router::$maximumVersion) {
                                $this->requestedApiVersion = $version;
                                $format = Scope::get(Router::$formatMap[$extension]);
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
                $format = Scope::get(Router::$formatMap['default']);
            }
        }
        if (empty($format)) {
            // RFC 2616: If an Accept header field is present, and if the
            // server cannot send a response which is acceptable according to
            // the combined Accept field value, then the server SHOULD send
            // a 406 (not acceptable) response.
            $format = Scope::get(Router::$formatMap['default']);
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

    abstract protected function stream($data);
}