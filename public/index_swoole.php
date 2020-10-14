<?php declare(strict_types=1);

use Luracast\Restler\Defaults;
use Luracast\Restler\Restler;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Convert;
use Swoole\Http\Request;
use Swoole\Http\Response;

require __DIR__ . '/../api/bootstrap.php';

Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

Defaults::$implementations[HttpClientInterface::class] = [SwooleHttpClient::class];

$http = new swoole_http_server("127.0.0.1", 8080);

$http->set([
    'worker_num' => 1, // The number of worker processes
    'daemonize' => false, // Whether start as a daemon process
    'backlog' => 128, // TCP backlog connection number
]);

$http->on('start', function ($server) {
    echo "Swoole http server is started at http://127.0.0.1:8080\n";
});

$http->on('request', function (Request $req, Response $res) {
    $request = Convert::toPSR7($req);
    echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
    (new Restler)->handle($request)->then(function (ResponseInterface $response) use ($res) {
        Convert::fromPSR7($response, $res);
    });
});

$http->start();
