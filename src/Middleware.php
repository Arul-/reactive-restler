<?php namespace Luracast\Restler;

use Psr\Http\Message\ServerRequestInterface;

class Middleware
{
    public function __invoke(ServerRequestInterface $request)
    {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        return (new Reactler)->handle($request);
    }
}