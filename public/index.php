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

    /**
     * @param bool $open
     * @return array
     */
    function bedroom($open = false)
    {
        return compact('open');
    }

    /**
     * @param array $param {@from body}
     * @return array
     */
    function post(array $param)
    {
        return compact('param');
    }
}

$r->addAPIClass('Home');

$loop = React\EventLoop\Factory::create();

$server = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) {
    echo $request->getUri() . PHP_EOL;

    $h = new Restle($request, new Response());
    return $h->handle();
});

$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();