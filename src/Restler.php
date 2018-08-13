<?php namespace Luracast\Restler;


use Luracast\Restler\Contracts\ComposerInterface;
use Luracast\Restler\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Restler extends Core
{

    protected function get(): void
    {
        $this->path = $this->getPath($_SERVER['REQUEST_URI']); //TODO: double check
        $this->query = $this->getQuery($_GET);
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->requestFormat = $this->getRequestMediaType($_SERVER["CONTENT_TYPE"]);
        $this->body = $this->getBody('');//TODO: Fix this
    }

    protected function negotiate(): void
    {
        $this->negotiateCORS(
            $this->requestMethod,
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '',
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '',
            $_SERVER['HTTP_ORIGIN'] ?? ''
        );
        $this->responseFormat = $this->negotiateResponseMediaType(
            $_SERVER['REQUEST_URI'], //TODO: double check
            $_SERVER['HTTP_ACCEPT']
        );
        $this->negotiateCharset($_SERVER['HTTP_ACCEPT_CHARSET']);
        $this->negotiateLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);

    }

    protected function compose($response = null)
    {
        $this->composeHeaders(
            $this->apiMethodInfo,
            $_SERVER['HTTP_ORIGIN'] ?? ''
        );
        /** @var ComposerInterface $compose */
        $compose = $this->make(ComposerInterface::class);
        return is_null($response) && App::$emptyBodyForNullResponse
            ? null
            : $compose->response($response);
    }

    protected function respond($response = []): ResponseInterface
    {
        $body = is_null($response) ? '' : $this->responseFormat->encode($response, !App::$productionMode);

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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new HttpException(501, 'not supported.');
    }
}