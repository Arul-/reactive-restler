<?php namespace Luracast\Restler;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use LogicalSteps\Async\Async;
use Luracast\Restler\Contracts\ComposerInterface;
use Luracast\Restler\Contracts\MiddlewareInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use Throwable;
use function GuzzleHttp\Psr7\stream_for;

class Restler extends Core
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;
    protected $rawRequestBody = "";

    public static $middleware = [];


    /**
     * @throws HttpException
     * @throws Exception
     */
    protected function get(): void
    {
        $scriptName = $this->request->getServerParams()['SCRIPT_NAME'] ?? '';
        $this->_path = $this->getPath($this->request->getUri(), $scriptName);
        $this->query = $this->getQuery($this->request->getQueryParams());
        $this->requestFormat = $this->getRequestMediaType($this->request->getHeaderLine('content-type'));
        $this->body = $this->getBody($this->rawRequestBody);
    }

    /**
     * @throws Exception
     * @throws HttpException
     */
    protected function negotiate(): void
    {
        $this->negotiateCORS(
            $this->_requestMethod,
            $this->request->getHeaderLine('Access-Control-Request-Method'),
            $this->request->getHeaderLine('Access-Control-Request-Headers'),
            $this->request->getHeaderLine('origin')
        );
        $this->responseFormat = $this->negotiateResponseMediaType(
            $this->request->getUri()->getPath(),
            $this->request->getHeaderLine('accept')
        );
        $this->negotiateCharset($this->request->getHeaderLine('accept-charset'));
        $this->negotiateLanguage($this->request->getHeaderLine('accept-language'));
    }

    protected function compose($response = null)
    {
        $this->composeHeaders(
            $this->_route,
            $this->request->getHeaderLine('origin')
        );
        /** @var ComposerInterface $compose */
        $compose = $this->make(ComposerInterface::class);
        return is_null($response) && Defaults::$emptyBodyForNullResponse
            ? null
            : $compose->response($response);
    }

    /**
     * @param array $response
     * @return ResponseInterface
     * @throws Exception
     */
    protected function respond($response = []): ResponseInterface
    {
        try {
            $body = is_null($response) ? '' : $this->responseFormat->encode($response, !Defaults::$productionMode);
        } catch (Throwable $throwable) {
            $body = json_encode($this->message($throwable, $this->request->getHeaderLine('origin')));
        }
        //handle throttling
        if ($throttle = $this->defaults->throttle ?? 0) {
            $elapsed = time() - $this->startTime;
            if ($throttle / 1e3 > $elapsed) {
                usleep(1e6 * ($throttle / 1e3 - $elapsed));
            }
        }
        if ($this->_responseCode == 401 && !isset($this->_responseHeaders['WWW-Authenticate'])) {
            $authString = count($this->router->authClasses)
                ? $this->router->authClasses[0]::getWWWAuthenticateString()
                : 'Unknown';
            $this->_responseHeaders['WWW-Authenticate'] = $authString;
        }
        return $this->container->make(ResponseInterface::class,
            [$this->_responseCode, $this->_responseHeaders, (string)$body]);
    }

    protected function stream($data): ResponseInterface
    {
        return $this->container->make(ResponseInterface::class,
            [$this->_responseCode, $this->_responseHeaders, $data ?? '']);
    }

    public function handle(ServerRequestInterface $request = null): PromiseInterface
    {
        if (!$request) {
            $request = ServerRequest::fromGlobals();
            if (isset($GLOBALS['HTTP_RAW_REQUEST_DATA'])) {
                $request = $request->withBody(stream_for($GLOBALS['HTTP_RAW_REQUEST_DATA']));
            }
        } elseif (is_null($this->defaults->returnResponse)) {
            $this->defaults->returnResponse = true;
        }
        $middleware = static::$middleware;
        $middleware[] = [$this, '_handle'];
        $promise = $this->handleMiddleware($middleware, $request);
        $promise = $promise->then(
            function ($result) {
                if ($result instanceof ResponseInterface) {
                    return $result;
                }
                if ($result instanceof Throwable) {
                    $result = $this->message($result, '');
                }
                return $this->respond($result);
            },
            function ($error) {
                if ($error instanceof Throwable) {
                    $error = $this->message($error, '');
                } else {
                    $error = new HttpException(500, (string)$error);
                }
                return $this->respond($error);
            }
        );
        if (true === $this->defaults->returnResponse) {
            return $promise;
        }
        $promise->then(function ($response) {
            die(Dump::response($response, true, false));
        });
        return $promise;
    }

    /**
     * @param ServerRequestInterface $request
     * @return PromiseInterface
     * @throws Exception
     */
    public function _handle(ServerRequestInterface $request)
    {
        $this->container->instance(ServerRequestInterface::class, $request);
        $body = $request->getBody();
        $this->rawRequestBody = $body->getContents();
        if ($body->isSeekable()) {
            $body->rewind();
        } elseif ($body->isWritable()) {
            $body->write($this->rawRequestBody);
        }
        $this->_requestMethod = $request->getMethod();
        $this->request = $request;
        try {
            try {
                $this->get();
                if (true === $this->defaults->useVendorMIMEVersioning) {
                    try {
                        $this->responseFormat = $this->negotiateResponseMediaType(
                            $this->request->getUri()->getPath(),
                            $this->request->getHeaderLine('accept')
                        );
                    } catch (Throwable $t) {
                        //ignore
                    }
                }
                $this->route();
            } catch (Throwable $t) {
                $this->negotiate();
                throw $t;
            }
            $this->negotiate();
            $this->filter($this->request);
            $this->authenticate($this->request);
            $this->filter($this->request, true);
            $this->validate();
            $data = $this->call($this->_route);
            if ($data instanceof ResponseInterface) {
                $this->composeHeaders(null, $this->request->getHeaderLine('origin'));
                $headers = $data->getHeaders() + $this->_responseHeaders;
                $data = $this->container->make(ResponseInterface::class,
                    [$data->getStatusCode(), $headers, $data->getBody()]);
                return new FulfilledPromise($data);
            }
            if (is_resource($data) && get_resource_type($data) == 'stream') {
                return new FulfilledPromise($this->stream($data));
            }
            return Async::await($data)->then(function ($data) {
                $data = $this->compose($data);
                return $this->respond($data);
            });
        } catch (Throwable $error) {
            $this->_exception = $error;
            if (!$this->responseFormat) {
                $this->responseFormat = $this->container->make(Json::class);
            }
            return new FulfilledPromise($this->respond(
                $this->message(
                    $error,
                    $this->request->getHeaderLine('origin')
                )
            ));
        }
    }

    /** @internal */
    public function handleMiddleware(
        array $middleware,
        ServerRequestInterface $request,
        $position = 0
    ): PromiseInterface {
        // final request handler will be invoked without a next handler
        if (!isset($middleware[$position + 1])) {
            $handler = $middleware[$position];
            return $handler($request);
        }

        $that = $this;
        $next = function (ServerRequestInterface $request) use ($that, $middleware, $position) {
            return $that->handleMiddleware($middleware, $request, $position + 1);
        };

        // invoke middleware request handler with next handler
        $handler = $middleware[$position];
        if (is_object($handler) && $handler instanceof MiddlewareInterface) {
            return $handler($request, $next, $this->container);
        }
        return $handler($request, $next);
    }

}