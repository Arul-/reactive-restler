<?php

use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Defaults;
use Luracast\Restler\RestException;
use Luracast\Restler\Scope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Restler
{
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

    public function __construct(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->path = ltrim((string)$request->getUri()->getPath(), '/');
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        $this->response = $response;
    }

    protected function get()
    {
    }

    protected function route()
    {
    }

    protected function negotiate()
    {
    }

    protected function authenticate()
    {
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

    protected function call()
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

    protected function compose()
    {

    }

    protected function composeException($e)
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
        $this->response = $this->response
            ->withStatus($e->getCode())
            ->withHeader('Content-Type', 'application/json');
    }

    protected function respond()
    {
    }

    public function handle()
    {
        try {
            $this->get();
        } catch (Exception $e) {
            return $this->composeException($e);
        } catch (Throwable $error) {
            return $this->composeException($error);
        }
    }
}