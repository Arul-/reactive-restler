<?php declare(strict_types=1);

use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\Obj;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\iFormat;
use Luracast\Restler\Format\UrlEncodedFormat;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes;
use Luracast\Restler\Scope;
use Luracast\Restler\Util;

class Core
{
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
    protected $responseFormat;
    /**
     * @var array
     */
    protected $writableMimeTypes = [];
    protected $path = '';
    protected $formatOverridesMap = ['extensions' => []];
    /**
     * @var iFormat
     */
    protected $requestFormat;
    protected $body = [];
    protected $formatMap = [];
    protected $query = [];
    protected $requestedApiVersion = 1;
    protected $apiVersionMap = [];
    protected $authClasses = [];

    /**
     * @param array ...$formats
     *
     * @throws Exception
     * @throws RestException
     */
    public function setSupportedFormats(string ...$formats)
    {
        $extensions = [];
        $throwException = $this->requestFormatDiffered;
        $this->writableMimeTypes = $this->readableMimeTypes = [];
        foreach ($formats as $className) {

            $obj = Scope::get($className);

            if (!$obj instanceof iFormat)
                throw new Exception('Invalid format class; must implement ' .
                    'iFormat interface');
            if ($throwException && get_class($obj) == get_class($this->requestFormat)) {
                $throwException = false;
            }

            foreach ($obj->getMIMEMap() as $mime => $extension) {
                if ($obj->isWritable()) {
                    $this->writableMimeTypes[] = $mime;
                    $extensions[".$extension"] = true;
                }
                if ($obj->isReadable())
                    $this->readableMimeTypes[] = $mime;
                if (!isset($this->formatMap[$extension]))
                    $this->formatMap[$extension] = $className;
                if (!isset($this->formatMap[$mime]))
                    $this->formatMap[$mime] = $className;
            }
        }
        if ($throwException) {
            throw new RestException(
                403,
                'Content type `' . $this->requestFormat->getMIME() . '` is not supported.'
            );
        }
        $this->formatMap['default'] = $formats[0];
        $this->formatMap['extensions'] = array_keys($extensions);
    }

    protected function negotiateResponseFormat() //response format
    {
        //check if the api method insists on response format using @format comment
        if (array_key_exists('format', $this->apiMethodInfo->metadata)) {
            $formats = explode(',', (string)$this->apiMethodInfo->metadata['format']);
            foreach ($formats as $i => $f) {
                $f = trim($f);
                if (!in_array($f, $this->formatOverridesMap))
                    throw new RestException(
                        500,
                        "Given @format is not present in overriding formats. Please call `\$r->setOverridingFormats('$f');` first."
                    );
                $formats[$i] = $f;
            }
            call_user_func_array(array($this, 'setSupportedFormats'), $formats);
        }

        // check if client has specified an extension
        /** @var $format iFormat */
        $format = null;
        $extensions = explode(
            '.',
            parse_url($this->request->getUri()->getPath(), PHP_URL_PATH)
        );
        while ($extensions) {
            $extension = array_pop($extensions);
            $extension = explode('/', $extension);
            $extension = array_shift($extension);
            if ($extension && isset($this->formatMap[$extension])) {
                $format = Scope::get($this->formatMap[$extension]);
                $format->setExtension($extension);
                // echo "Extension $extension";
                return $format;
            }
        }
        // check if client has sent list of accepted data formats
        if ($this->request->hasHeader('accept')) {
            $acceptLine = $this->request->getHeaderLine('accept');
            $acceptList = Util::sortByPriority($acceptLine);
            foreach ($acceptList as $accept => $quality) {
                if (isset($this->formatMap[$accept])) {
                    $format = Scope::get($this->formatMap[$accept]);
                    $format->setMIME($accept);
                    // Tell cache content is based on Accept header
                    $this->response = $this->response->withHeader('Vary', 'Accept');

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
                                $this->response = $this->response->withHeader('Vary', 'Accept');

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
            $acceptLine = '*/*';
        }
        if (strpos($acceptLine, '*') !== false) {
            if (false !== strpos($acceptLine, 'application/*')) {
                $format = Scope::get('JsonFormat');
            } elseif (false !== strpos($acceptLine, 'text/*')) {
                $format = Scope::get('XmlFormat');
            } elseif (false !== strpos($acceptLine, '*/*')) {
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
            throw new RestException(
                406,
                'Content negotiation failed. ' .
                'Try `' . $format->getMIME() . '` instead.'
            );
        } else {
            // Tell cache content is based at Accept header
            $this->response = $this->response->withHeader('Vary', 'Accept');
            return $format;
        }
    }

    /**
     * Sets the cleaned up path without extensions and unwanted slashes
     * @param string $path
     */
    protected function getPath(string $path): void
    {
        $this->path = str_replace(
            array_merge(
                $this->formatMap['extensions'],
                $this->formatOverridesMap['extensions']
            ),
            '',
            trim($path, '/')
        );
    }

    protected function getQuery(array $get = []): void
    {
        $this->query = UrlEncodedFormat::decoderTypeFix($get);
        //parse defaults
        foreach ($this->query as $key => $value) {
            if (isset(Defaults::$aliases[$key])) {
                unset($this->query[$key]);
                $this->query[Defaults::$aliases[$key]] = $value;
                $key = Defaults::$aliases[$key];
            }
            if (in_array($key, Defaults::$overridables)) {
                Defaults::setProperty($key, $value);
            }
        }
    }

    protected function getBody(string $raw = ''): void
    {
        $r = [];
        if ($this->requestMethod == 'PUT'
            || $this->requestMethod == 'PATCH'
            || $this->requestMethod == 'POST'
        ) {
            //TODO: handle stream
            //TODO: find and use request format
            $r = Obj::toArray(json_decode($raw));

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
        $this->body = $r;
    }

    protected function route(): void
    {
        $this->apiMethodInfo = $o = Routes::find(
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
        if (!isset($o->className))
            throw new RestException(404);

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
}