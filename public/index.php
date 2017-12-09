<?php

use Luracast\Restler\Defaults;
use Luracast\Restler\RestException;
use Luracast\Restler\Restler;
use Luracast\Restler\Routes;
use Luracast\Restler\Scope;
use React\Http\Response;

include __DIR__ . "/../vendor/autoload.php";

$r = new Restler();

class Home
{
    function get()
    {
        return ['success' => true];
    }

    /**
     * @param int $id
     * @return array
     */
    function kitchen($id)
    {
        return compact('id');
    }
}

$r->addAPIClass('Home');

$loop = React\EventLoop\Factory::create();

$server = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) {
    echo $request->getUri() . PHP_EOL;

    try {
        $path = ltrim((string)$request->getUri()->getPath(), '/');
        $route = Routes::find($path, $request->getMethod());
        echo var_dump($route);
        //return new Response(200, ['Content-Type' => 'text/json'], json_encode($route, JSON_PRETTY_PRINT));
        $accessLevel = max(Defaults::$apiAccessLevel, $route->accessLevel);
        $object =  Scope::get($route->className);
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
        return new Response(200, ['Content-Type' => 'text/json'], json_encode($result, JSON_PRETTY_PRINT));

    } catch (RestException $e) {
        $compose = Scope::get(Defaults::$composeClass);
        $message = json_encode(
            $compose->message($e),
            JSON_PRETTY_PRINT
        );
        return new Response($e->getCode(), ['Content-Type' => 'text/json'], $message);
    }


    return new React\Http\Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello World!\n"
    );
});

$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();