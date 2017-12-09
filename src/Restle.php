<?php


use Luracast\Restler\Data\ApiMethodInfo;
use Luracast\Restler\Defaults;
use Luracast\Restler\Format\UrlEncodedFormat;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes;
use Luracast\Restler\Scope;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Restle
{
    protected $requestMethod = 'GET';
    protected $path = '';
    protected $requestData = [];

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
            $r = $this->request->getParsedBody();

            $r = is_array($r)
                ? array_merge($r, array(Defaults::$fullRequestDataName => $r))
                : array(Defaults::$fullRequestDataName => $r);
            return $includeQueryParameters
                ? $r + $get
                : $r;
        }
        return $includeQueryParameters ? $get : array(); //no body
    }

    protected function route()
    {
        return Routes::find($this->path, $this->requestMethod, 1, $this->getRequestData());
    }

    public function handle()
    {
        try {
            $route = $this->route();
            $accessLevel = max(Defaults::$apiAccessLevel, $route->accessLevel);
            $object = Scope::get($route->className);
            switch ($accessLevel) {
                case 3 : //protected method
                    $reflectionMethod = new \ReflectionMethod(
                        $object,
                        $route->methodName
                    );
                    $reflectionMethod->setAccessible(true);
                    $result = $reflectionMethod->invokeArgs(
                        $object,
                        $route->parameters
                    );
                    break;
                default :
                    $result = call_user_func_array(array(
                        $object,
                        $route->methodName
                    ), $route->parameters);
            }
            $this->response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT));
            return $this->response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        } catch (RestException $e) {
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
}