<?php

namespace Luracast\Restler\Middleware;


use Luracast\Restler\Contracts\ContainerInterface;
use Luracast\Restler\Contracts\MiddlewareInterface;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\PassThrough;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function React\Promise\resolve;

class StaticFiles implements MiddlewareInterface
{
    /**
     * @var string
     */
    private $webRoot;

    public function __construct(string $webRoot)
    {
        $this->webRoot = rtrim($webRoot, DIRECTORY_SEPARATOR);
    }

    public function __invoke(
        ServerRequestInterface $request,
        callable $next = null,
        ContainerInterface $container = null
    )
    {
        $path = $request->getUri()->getPath();
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (isset(PassThrough::$mimeTypes[$extension])) {
            $path = $this->webRoot . DIRECTORY_SEPARATOR . $path;
            if (is_file($path)) {
                try {
                    $response = PassThrough::file($path);
                    return resolve($response);
                } catch (\Throwable $throwable) {
                    //ignore
                }
            }
        }
        if ($next) {
            return resolve($next($request));
        }
        $class = ClassName::get(ResponseInterface::class);
        return resolve(new $class(404, ['Content-Type' => 'text/html'], '<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>404 Not Found</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <h1 id="http-error-heading">404 Not Found</h1>
        <p>Sorry! Page not found</p>
    </body>
</html>'));
    }
}