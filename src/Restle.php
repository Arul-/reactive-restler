<?php declare(strict_types=1);


use Luracast\Restler\CommentParser;
use Luracast\Restler\Data\ValidationInfo;
use Luracast\Restler\Data\Validator;
use Luracast\Restler\Defaults;
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


    public function __construct(ServerRequestInterface $request, ResponseInterface $response, $rawRequestBody = '')
    {
        $this->rawRequestBody = $rawRequestBody;
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        $this->response = $response;
    }

    protected function get(): void
    {
        $this->getPath($this->request->getUri()->getPath());
        $this->getQuery($this->request->getQueryParams());
        $this->getBody($this->rawRequestBody);
    }

    protected function route(): void
    {
        parent::route();
    }

    protected function negotiate(): void
    {
        $this->negotiateResponseFormat(
            $this->request->getUri()->getPath(),
            $this->request->getHeaderLine('accept')
        );
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
            $this->get();
            $this->route();
            $this->negotiate();
            $this->validate();
            $this->compose($this->call());
        } catch (Throwable $error) {
            $this->composeMessage($error);
        }
        return $this->response;
    }

    /**
     * @param array|string $response
     */
    protected function compose($response = []): void
    {
        $this->response->getBody()->write($this->responseFormat->encode($response), true);
        foreach ($this->responseHeaders as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
        $this->response = $this->response
            ->withStatus($this->responseCode)
            ->withHeader('Content-Type', $this->responseFormat->getMIME());
    }

    protected function composeMessage(Throwable $e): void
    {
        if (!$e instanceof RestException) {
            $e = new RestException(500, $e->getMessage(), [], $e);
        }
        $this->responseCode = $e->getCode();
        $compose = Scope::get(Defaults::$composeClass);
        $this->compose($compose->message($e));
    }

}