<?php namespace Luracast\Restler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class Middleware implements RequestHandlerInterface, MiddlewareInterface
{
    /**
     * React PHP Middleware interface
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request)
    {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        return (new Reactler)->handle($request);
    }

    /**
     * PSR-15 Request Handler Interface to take a request and return a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new Reactler)->handle($request);
    }

    /**
     * PSR-15 Middleware Interface to process an incoming server request and return a response,
     * optionally delegating response creation to a handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return (new Reactler)->handle($request);
        } catch (Throwable $throwable) {
            return $handler->handle($request->withAttribute('Exception', $throwable));
        }
    }
}