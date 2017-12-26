<?php declare(strict_types=1);


use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\JsonFormat;
use Luracast\Restler\RestException;
use Luracast\Restler\Scope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Restle extends Core
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;
    /**
     * @var ResponseInterface
     */
    protected $response;
    protected $rawRequestBody = "";


    public function __construct(Restle $master = null)
    {
        if ($master) {
            $this->authClasses = $master->authClasses;
            $this->apiVersionMap = $master->apiVersionMap;
            $this->formatMap = $master->formatMap;
        }
    }

    /**
     * @throws HttpException
     */
    protected function get(): void
    {
        $this->path = $this->getPath($this->request->getUri()->getPath());
        $this->query = $this->getQuery($this->request->getQueryParams());
        $this->requestFormat = $this->getRequestFormat($this->request->getHeaderLine('content-type'));
        $this->body = $this->getBody($this->rawRequestBody);
    }

    /**
     * @throws HttpException
     */
    protected function route(): void
    {
        parent::route();
    }

    /**
     * @throws HttpException
     */
    protected function negotiate(): void
    {
        $this->negotiateCORS(
            $this->requestMethod,
            $this->request->getHeaderLine('Access-Control-Request-Method'),
            $this->request->getHeaderLine('Access-Control-Request-Headers'),
            $this->request->getHeaderLine('origin')
        );
        $this->responseFormat = $this->negotiateResponseFormat(
            $this->request->getUri()->getPath(),
            $this->request->getHeaderLine('accept')
        );
        $this->negotiateCharset($this->request->getHeaderLine('accept-charset'));
        $this->negotiateLanguage($this->request->getHeaderLine('accept-language'));
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

    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $rawRequestBody = ''
    ): ResponseInterface {
        $this->rawRequestBody = $rawRequestBody;
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        $this->response = $response;
        try {
            if (empty($this->formatMap)) {
                $this->setSupportedFormats('JsonFormat');
            }
            $this->get();
            $this->route();
            $this->negotiate();
            $this->authenticate();
            $this->validate();
            if (!$this->responseFormat) {
                $this->responseFormat = new JsonFormat();
            }
            return $this->respond($this->compose($this->call()));
        } catch (Throwable $error) {
            if (!$this->responseFormat) {
                $this->responseFormat = new JsonFormat();
            }
            return $this->respond($this->message($error));
        }
    }

    /**
     * @param mixed $response
     * @return mixed
     */
    protected function compose($response = null)
    {
        $this->composeHeaders(
            $this->apiMethodInfo,
            $this->request->getHeaderLine('origin'),
            $this->request->getProtocolVersion()
        );
        /**
         * @var iCompose Default Composer
         */
        $compose = Scope::get(Defaults::$composeClass);
        return is_null($response) && Defaults::$emptyBodyForNullResponse
            ? null
            : $compose->response($response);
    }

    protected function message(Throwable $e)
    {
        if (!$e instanceof RestException) {
            $e = new HttpException(500, $e->getMessage(), [], $e);
        }
        $this->responseCode = $e->getCode();
        if ($this->responseCode == 200 && empty($e->getMessage())) {
            return null;
        }
        $this->composeHeaders(
            $this->apiMethodInfo,
            $this->request->getHeaderLine('origin'),
            $this->request->getProtocolVersion(),
            $e
        );
        /**
         * @var iCompose Default Composer
         */
        $compose = Scope::get(Defaults::$composeClass);
        return $compose->message($e);
    }

    protected function respond($response = []): ResponseInterface
    {
        if (!is_null($response)) {
            $this->response->getBody()->write($this->responseFormat->encode($response, true));
        }
        if ($this->responseCode == 401 && !isset($this->responseHeaders['WWW-Authenticate'])) {
            $authString = count($this->authClasses)
                ? Scope::get($this->authClasses[0])->__getWWWAuthenticateString()
                : 'Unknown';
            $this->responseHeaders['WWW-Authenticate'] = $authString;
        }
        foreach ($this->responseHeaders as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
        return $this->response;//->withHeader('Content-Type', $this->responseFormat->getMIME());;
    }

    public function __call($name, $arguments)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
        return null;
    }

    public function getEvents()
    {
        return [];
    }

    public function getProductionMode()
    {
        return false;
    }
}