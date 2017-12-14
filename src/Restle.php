<?php


use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\Obj;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\iFormat;
use Luracast\Restler\Format\UrlEncodedFormat;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes;
use Luracast\Restler\Scope;
use Luracast\Restler\Util;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Restle
{
    protected $rawRequestBody;
    protected $requestMethod = 'GET';
    protected $path = '';
    protected $requestData = [];
    protected $queryParams;
    /**
     * @var ApiMethodInfo
     */
    protected $apiMethodInfo;

    /**
     * @var ServerRequestInterface
     */
    protected $request;
    /**
     * @var ResponseInterface
     */
    protected $response;


	protected $formatMap = [];
	protected $formatOverridesMap = ['extensions' => []];

    /**
     * @var iFormat
     */
    protected $responseFormat;
    /**
     * @var iFormat
     */
    protected $requestFormat;
    /**
     * @var bool
     */
    protected $requestFormatDiffered = false;
    /**
     * @var array
     */
    protected $writableMimeTypes = [];
    /**
     * @var array
     */
    protected $readableMimeTypes = [];

    public function __construct(ServerRequestInterface $request, ResponseInterface $response, $rawRequestBody = '')
    {
        $this->rawRequestBody = $rawRequestBody;
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        $this->response = $response;
    }

    protected function getRequestData($includeQueryParameters = true)
    {
        $this->queryParams = UrlEncodedFormat::decoderTypeFix($this->request->getQueryParams());
        //parse defaults
        foreach ($this->queryParams as $key => $value) {
            if (isset(Defaults::$aliases[$key])) {
                unset($this->queryParams[$key]);
                $this->queryParams[Defaults::$aliases[$key]] = $value;
                $key = Defaults::$aliases[$key];
            }
            if (in_array($key, Defaults::$overridables)) {
                Defaults::setProperty($key, $value);
            }
        }
        if ($this->requestMethod == 'PUT'
            || $this->requestMethod == 'PATCH'
            || $this->requestMethod == 'POST'
        ) {
            if (!empty($this->requestData)) {
                return $includeQueryParameters
                    ? $this->requestData + $this->queryParams
                    : $this->requestData;
            }

            //TODO: handle stream

            $r = Obj::toArray(json_decode($this->rawRequestBody));

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
            return $includeQueryParameters
                ? $r + $this->queryParams
                : $r;
        }
        return $includeQueryParameters ? $this->queryParams : array(); //no body
    }

    protected function route()
    {
        $this->apiMethodInfo = Routes::find($this->path, $this->requestMethod, 1, $this->getRequestData());
    }

    protected function negotiate()
    {
        $this->responseFormat = $this->negotiateResponseFormat();
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

    protected function validate()
    {
        if (!Defaults::$autoValidationEnabled) {
            return;
        }

        $o = &$this->apiMethodInfo;
        foreach ($o->metadata['param'] as $index => $param) {
            $info = &$param [CommentParser::$embeddedDataName];
            if (!isset ($info['validate'])
                || $info['validate'] != false
            ) {
                if (isset($info['method'])) {
                    $info ['apiClassInstance'] = Scope::get($o->className);
                }
                //convert to instance of ValidationInfo
                $info = new ValidationInfo($param);
                //initialize validator
                Scope::get(Defaults::$validatorClass);
                $validator = Defaults::$validatorClass;
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

    public function call()
    {
        $o = &$this->apiMethodInfo;
        $accessLevel = max(Defaults::$apiAccessLevel, $o->accessLevel);
        $object = Scope::get($o->className);
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

    public function handle()
    {
        try {
            if (empty($this->formatMap)) {
                $this->setSupportedFormats('JsonFormat');
            }
            $this->path = str_replace(
                array_merge(
                    $this->formatMap['extensions'],
                    $this->formatOverridesMap['extensions']
                ),
                '',
                trim($this->request->getUri()->getPath(), '/') //remove trailing slash if found
            );
            $this->requestData = $this->getRequestData(false);
            $this->route();
            $this->negotiate();
            $this->validate();
            $this->response->getBody()->write($this->responseFormat->encode($this->call(),true));
            return $this->response
                ->withStatus(200)
                ->withHeader('Content-Type', $this->responseFormat->getMIME());
        } catch (Exception $e) {
            return $this->compose($e);
        } catch (Throwable $error) {
            return $this->compose($error);
        }
    }

    private function compose($e)
    {
        if (!$e instanceof RestException) {
            $e = new RestException(500, $e->getMessage(), [], $e);
        }
        $compose = Scope::get(Defaults::$composeClass);
        $message = json_encode(
            $compose->message($e),
            JSON_PRETTY_PRINT
        );
        $this->response->getBody()->write($message);
        return $this->response
            ->withStatus($e->getCode())
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array ...$formats
     *
     * @throws Exception
     * @throws RestException
     */
    public function setSupportedFormats(...$formats)
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
                if($obj->isWritable()){
                    $this->writableMimeTypes[]=$mime;
                    $extensions[".$extension"] = true;
                }
                if($obj->isReadable())
                    $this->readableMimeTypes[]=$mime;
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
}