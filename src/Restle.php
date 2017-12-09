<?php


use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\UrlEncodedFormat;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes;
use Luracast\Restler\Scope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Restle
{
    protected $rawRequestBody;
    protected $requestMethod = 'GET';
    protected $path = '';
    protected $requestData = [];
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

    public function __construct(ServerRequestInterface $request, ResponseInterface $response, $rawRequestBody = '')
    {
        $this->rawRequestBody = $rawRequestBody;
        $this->path = ltrim((string)$request->getUri()->getPath(), '/');
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        $this->response = $response;
    }

    protected function getRequestData($includeQueryParameters = true)
    {
        $get = UrlEncodedFormat::decoderTypeFix($this->request->getQueryParams());
        if ($this->requestMethod == 'PUT'
            || $this->requestMethod == 'PATCH'
            || $this->requestMethod == 'POST'
        ) {
            if (!empty($this->requestData)) {
                return $includeQueryParameters
                    ? $this->requestData + $get
                    : $this->requestData;
            }

            //TODO: handle stream

            $r = json_decode($this->rawRequestBody);

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
            return $this->requestData = $includeQueryParameters
                ? $r + $get
                : $r;
        }
        return $includeQueryParameters ? $get : array(); //no body
    }

    protected function route()
    {
        $this->apiMethodInfo = Routes::find($this->path, $this->requestMethod, 1, $this->getRequestData());
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
        $this->route();
        $this->validate();
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
            $this->getRequestData();
            $this->response->getBody()->write(json_encode($this->call(), JSON_PRETTY_PRINT));
            return $this->response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
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
}