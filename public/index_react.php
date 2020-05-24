<?php

declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

use Luracast\Restler\Defaults;
use Luracast\Restler\Restler;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\StreamingServer;


$loop = React\EventLoop\Factory::create();

ReactHttpClient::setLoop($loop);
Defaults::$implementations[HttpClientInterface::class] = [ReactHttpClient::class];

$server = new StreamingServer(
    [
        new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
        new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
        new RequestBodyParserMiddleware(2 * 1024 * 1024, 1), //allow UPLOAD of only 1 file with max 2MB
        function (ServerRequestInterface $request) {
            echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
            return (new Restler)->handle($request);
        }
    ]
);

$server->on(
    'error',
    function (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    }
);


$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();
