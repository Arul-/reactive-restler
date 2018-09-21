<?php namespace Luracast\Restler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements RequestHandlerInterface
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
     * PSR 15 Request Handler Interface to take a request and return a response.
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new Reactler)->handle($request);
    }
}