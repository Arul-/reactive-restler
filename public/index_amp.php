<?php declare(strict_types=1);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
error_reporting(E_ALL);

use Amp\Http\Server\{Request, Response, Server, RequestHandler\CallableRequestHandler};
use Amp\Loop;
use function Amp\Socket\listen;
use Luracast\Restler\Defaults;
use Luracast\Restler\Reactler;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;

require __DIR__ . '/../api/bootstrap.php';

Defaults::$implementations[ResponseInterface::class] = [Response::class];

Loop::run(function () {
    $sockets = [
        listen("0.0.0.0:8080"),
        listen("[::]:8080"),
    ];

    $server = new Server($sockets, new CallableRequestHandler(function (Request $request) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        try {
            $r = (new Reactler)->handle($request);
            dump($r);
        } catch (Throwable $throwable) {
            dump($throwable);
        }
        return $r;
    }), new NullLogger);

    echo "Server running at http://127.0.0.1:8080\n";
    yield $server->start();

    // Stop the server gracefully when SIGINT is received.
    // This is technically optional, but it is best to call Server::stop().
    Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
        Loop::cancel($watcherId);
        yield $server->stop();
    });
});
echo ' bye' . PHP_EOL;

