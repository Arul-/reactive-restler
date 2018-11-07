<?php declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

use Luracast\Restler\Reactler;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\StreamingServer;


$loop = React\EventLoop\Factory::create();

$server = new StreamingServer([
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
    function (ServerRequestInterface $request) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        return (new Reactler)->handle($request);
    }
]);

$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});


$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();