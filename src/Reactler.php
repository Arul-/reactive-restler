<?php namespace Luracast\Restler;

use Exception;
use Luracast\Restler\Contracts\ComposerInterface;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\MediaTypes\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Reactler extends Core
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;
    protected $rawRequestBody = "";


    /**
     * @throws HttpException
     * @throws Exception
     */
    protected function get(): void
    {
        $this->path = $this->getPath($this->request->getUri()->getPath());
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
            $this->requestMethod,
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
            $this->apiMethodInfo,
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
     */
    protected function respond($response = []): ResponseInterface
    {
        $body = is_null($response) ? '' : $this->responseFormat->encode($response, !Defaults::$productionMode);

        //handle throttling
        if ($throttle = $this->app['throttle'] ?? 0) {
            $elapsed = time() - $this->startTime;
            if ($throttle / 1e3 > $elapsed) {
                usleep(1e6 * ($throttle / 1e3 - $elapsed));
            }
        }
        if ($this->responseCode == 401 && !isset($this->responseHeaders['WWW-Authenticate'])) {
            $authString = count($this->router['authClasses'])
                ? $this->router['authClasses'][0]::getWWWAuthenticateString()
                : 'Unknown';
            $this->responseHeaders['WWW-Authenticate'] = $authString;
        }
        return $this->container->make(ResponseInterface::class,
            [$this->responseCode, $this->responseHeaders, $body]);
    }

    protected function stream($data): ResponseInterface
    {
        return $this->container->make(ResponseInterface::class,
            [$this->responseCode, $this->responseHeaders, $data]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->container->instance(ServerRequestInterface::class, $request);
        $this->rawRequestBody = (string)$request->getBody();
        $this->requestMethod = $request->getMethod();
        $this->request = $request;
        try {
            try {
                $this->get();
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
            $data = $this->call($this->apiMethodInfo);
            if ($data instanceof ResponseInterface) {
                $this->composeHeaders(null, $this->request->getHeaderLine('origin'));
                $headers = $data->getHeaders() + $this->responseHeaders;
                $data = $this->container->make(ResponseInterface::class,
                    [$data->getStatusCode(), $headers, $data->getBody()]);
                return $data;
            }
            if (is_resource($data) && get_resource_type($data) == 'stream') {
                return $this->stream($data);
            }
            return $this->respond($this->compose($data));
        } catch (Throwable $error) {
            if (!$this->responseFormat) {
                $this->responseFormat = new Json();
            }
            return $this->respond(
                $this->message(
                    $error,
                    $this->request->getHeaderLine('origin')
                )
            );
        }
    }
}